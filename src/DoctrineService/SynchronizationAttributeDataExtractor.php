<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\DoctrineService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Gtt\SynchronizationPlugin\Contract\Attribute\AsSyncableEntity;
use Gtt\SynchronizationPlugin\Contract\SyncableEntityInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationConfigException;
use Gtt\SynchronizationPlugin\Processing\Client\SynchronizationClientInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

class SynchronizationAttributeDataExtractor
{
    /**
     * @var array<string, AsSyncableEntity>
     */
    private static array $attributesIntancesCache = [];

    /**
     * @var array<string, array<string>>
     */
    private static array $operationsCodesCache = [];

    /**
     * @codeCoverageIgnore
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MappingDriverChain $mappingDriver,
        private string $envMode,
        private ConsoleOutput $output = new ConsoleOutput(),
    )
    {
    }

    /**
     * @return array<string>
     */
    public function getRelationsNamesToSyncables(ResourceInterface $resource): array
    {
        $attribute = $this->getAttributeInstance($resource);

        if ($attribute instanceof AsSyncableEntity) {
            return array_keys($attribute->getOperations());
        }

        return [];
    }

    /**
     * @return array<string>
     */
    public function explode(string $path): array
    {
        if ($path === AsSyncableEntity::ROOT_PATH) {
            return [$path];
        }

        return explode(AsSyncableEntity::ROOT_PATH, $path);
    }

    /**
     * @return array<string, object>
     *
     * @throws SynchronizationConfigException
     */
    public function getRelationsToSyncables(ResourceInterface $resource): array
    {
        $relations = [];
        $object = $resource;

        foreach ($this->getRelationsNamesToSyncables($resource) as $pathToSyncable) {
            if ($pathToSyncable === AsSyncableEntity::ROOT_PATH) {
                if (!$resource instanceof SyncableEntityInterface) {
                    throw new SynchronizationConfigException(
                        $resource::class . ' has root sync path, but it does not support a SyncableEntityInterface',
                    );
                }

                return [];
            }

            foreach ($this->explode($pathToSyncable) as $subPath) {
                $accessor = 'get' . ucfirst($subPath);

                if (!method_exists($object, $accessor)) {
                    throw new SynchronizationConfigException(
                        sprintf(
                            '%s has sync path: %s, but method does not exist: %s:%s',
                            $resource::class,
                            $pathToSyncable,
                            $object::class,
                            $accessor
                        ),
                    );
                }

                $object = $object->{$accessor}();

                if (!is_object($object)) {
                    throw new SynchronizationConfigException($accessor . ' references on non-object attribute.');
                }

                $relations[$subPath] = $object;
            }
        }

        return $relations;
    }

    public function getSyncableEntity(
        ResourceInterface $resource,
        SynchronizationClientInterface $client,
    ): ?SyncableEntityInterface
    {
        $attribute = $this->getAttributeInstance($resource);

        if ($attribute instanceof AsSyncableEntity) {
            foreach ($attribute->getOperations() as $pathToSyncable => $operationCodes) {
                if (!in_array($client->getOperationCode(), $operationCodes, true)) {
                    continue;
                }

                if ($pathToSyncable === AsSyncableEntity::ROOT_PATH) {
                    if (!$resource instanceof SyncableEntityInterface) {
                        throw new SynchronizationConfigException(
                            $resource::class . ' has root sync path, but it does not support a SyncableEntityInterface',
                        );
                    }

                    return $resource;
                }

                $object = $resource;

                foreach ($this->explode($pathToSyncable) as $subPath) {
                    $accessor = 'get' . ucfirst($subPath);

                    if (!method_exists($object, $accessor)) {
                        throw new SynchronizationConfigException(
                            sprintf(
                                '%s has sync path: %s, but method does not exist: %s:%s',
                                $resource::class,
                                $pathToSyncable,
                                $object::class,
                                $accessor
                            ),
                        );
                    }

                    $object = $object->{$accessor}();

                    if (!is_object($object)) {
                        throw new SynchronizationConfigException($accessor . ' references on non-object attribute.');
                    }
                }

                if (!$object instanceof SyncableEntityInterface) {
                    $template = '%s has sync path %s, but destination item is not a SyncableEntityInterface';

                    throw new SynchronizationConfigException(sprintf($template, $resource::class, $pathToSyncable));
                }

                return $object;
            }
        }

        return null;
    }

    public function hasSyncableAttributeAnnotation(ResourceInterface $resource): bool
    {
        return $this->extractOperationCodes($resource) !== [];
    }

    /**
     * @param array<string> $operationsList
     *
     * @throws ReflectionException if the class does not exist.
     */
    public function checkEntitiesConfigurations(array $operationsList): ?bool
    {
        $entityClassnameCollection = array_filter(
            $this->mappingDriver->getAllClassNames(),
            fn(string $className) => in_array(
                ResourceInterface::class,
                class_implements($className) === false ? [] : class_implements($className),
                true,
            ),
        );

        /**
         * Example: ['App\EntityTest' => ['transitRelation.targetRelation' => ['TEST_ENTITY_TO_FILE_UPDATE']]]
         *
         * @var array<string, array<string, array<string>>> $attributes
         */
        $attributes = [];
        $errors = [];

        foreach ($entityClassnameCollection as $className) {
            $reflectionAttributes = (new ReflectionClass($className))->getAttributes(AsSyncableEntity::class);
            $attribute = reset($reflectionAttributes);

            if ($attribute instanceof ReflectionAttribute) {
                $arguments = $attribute->getArguments();

                if (!isset($arguments['operations'])) {
                    $errors[] = 'AsSyncableEntity class needs $operations parameter. Invalid for: ' . $className;
                }

                $attributes[$className] = $arguments['operations'];
            }
        }

        foreach ($attributes as $className => $operationsData) {
            $targetClassName = $className;

            foreach ($operationsData as $path => $operations) {
                if (count(array_intersect($operations, $operationsList)) !== count($operations)) {
                    $errors[] = $className
                        . ' has invalid operations codes. Recheck it by SynchronizationClientInterface instances.';
                }

                if ($path === AsSyncableEntity::ROOT_PATH) {
                    if (!is_a($className, SyncableEntityInterface::class, true)) {
                        $errors[] = $className . ' is not SyncableEntityInterface, but has path "."';
                    }

                    break 1;
                }

                foreach ($this->explode($path) as $subPath) {
                    $accessor = 'get' . ucfirst($subPath);

                    if (!method_exists($targetClassName, $accessor)) {
                        $errors[] = $targetClassName . ' does not contain method: ' . $accessor;
                        $targetClassName = null;

                        break 2;
                    }

                    try {
                        $relationClassName = $this->entityManager
                            ->getClassMetadata($targetClassName)
                            ->getAssociationTargetClass($subPath);

                        $targetClassName = $relationClassName;
                    } catch (Throwable $throwable) {
                        $errors[] = sprintf(
                            '%s class has error with relation "%s": %s',
                            $targetClassName,
                            $subPath,
                            $throwable->getMessage(),
                        );

                        $targetClassName = null;

                        break 2;
                    }
                }
            }

            if (
                $targetClassName !== null
                && !is_a($targetClassName, SyncableEntityInterface::class, true)
            ) {
                $errors[] = $targetClassName
                    . ' is not SyncableEntityInterface, but was set as target syncable entity for class '
                    . $className;
            }
        }

        if (strtolower($this->envMode) !== 'test' && $errors !== []) {
            foreach ($errors as $error) {
                $this->output->writeln(sprintf('<error>[SYNC ERROR] %s </error>', $error));
            }
        }

        if (strtolower($this->envMode) !== 'prod' && $errors !== []) {
            throw new SynchronizationConfigException('Errors were found: ' . implode('; ', $errors));
        }

        return true;
    }

    /**
     * @return array<string>
     */
    public function extractOperationCodes(ResourceInterface $resource): array
    {
        if (array_key_exists($resource::class, self::$operationsCodesCache)) {
            return self::$operationsCodesCache[$resource::class];
        }

        $attribute = $this->getAttributeInstance($resource);

        if ($attribute === null) {
            self::$operationsCodesCache[$resource::class] = [];

            return [];
        }

        assert($attribute instanceof AsSyncableEntity);

        $operations = [];

        foreach ($attribute->getOperations() as $operationCodes) {
            $operations = [...$operations, ...$operationCodes];
        }

        self::$operationsCodesCache[$resource::class] = array_unique($operations);

        return self::$operationsCodesCache[$resource::class];
    }

    private function getAttributeInstance(ResourceInterface $resource): ?AsSyncableEntity
    {
        if (
            array_key_exists($resource::class, self::$attributesIntancesCache)
            && self::$attributesIntancesCache[$resource::class] instanceof AsSyncableEntity
        ) {
            return self::$attributesIntancesCache[$resource::class];
        }

        $attributes = (new ReflectionObject($resource))->getAttributes(AsSyncableEntity::class);

        if ($attributes !== []) {
            self::$attributesIntancesCache[$resource::class] = reset($attributes)->newInstance();

            return self::$attributesIntancesCache[$resource::class];
        }

        return null;
    }
}
