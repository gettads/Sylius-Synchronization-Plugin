<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Processing\Client;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use Gtt\SynchronizationPlugin\DoctrineEventListener\DTO\EntityChangeCollectionDto;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableOutputInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationCorruptedException;
use Gtt\SynchronizationPlugin\Exception\SynchronizationInvalidOutputException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

abstract class BaseSynchronizationOutputClient implements SynchronizationOutputClientInterface
{
    public const CODE = '';

    protected const TYPE = '';

    protected const CRUD_TYPES = [];

    protected Serializer $serializer;

    /**
     * @throws SynchronizationInvalidOutputException
     */
    abstract public function buildTransferableOutput(
        EntityChangeCollectionDto $appliedOnlyChanges,
        EntityChangeCollectionDto $allChronologyChanges,
        SynchronizationInterface $synchronization,
    ): ?TransferableOutputInterface;

    abstract public function synchronizeOutput(TransferableOutputInterface $dto): void;

    abstract public function getTransferEnvelopDtoClass(): string;

    abstract public function getTransferItemDtoClass(): string;

    public function __construct(
        protected ClientInterface $httpClient,
        protected SynchronizationAttributeDataExtractor $extractor,
        protected EntityManagerInterface $entityManager,
        protected LoggerInterface $logger,
    ) {
        $this->initSerializer();
    }

    public function getOperationCode(): string
    {
        if (static::CODE === '') {
            throw new SynchronizationCorruptedException(
                'Using of uninitialized SynchronizationClientInterface::CODE is not allowed.',
            );
        }

        return static::CODE;
    }

    public function getType(): string
    {
        if (static::TYPE === '') {
            throw new SynchronizationCorruptedException(
                'Using of uninitialized SynchronizationClientInterface::TYPE is not allowed.',
            );
        }

        return static::TYPE;
    }

    /**
     * @inheritDoc
     */
    public function getCrudTypes(): array
    {
        if (static::CRUD_TYPES === []) {
            throw new SynchronizationCorruptedException(
                'Using of uninitialized SynchronizationClientInterface::CRUD_TYPES is not allowed.',
            );
        }

        return static::CRUD_TYPES;
    }

    public function isSupported(EntityChangeCollectionDto $dto): bool
    {
        foreach ($dto->getChanges() as $change) {
            $resource = $change->getResource();
            $operations = $this->extractor->extractOperationCodes($resource);

            foreach ($operations as $operation) {
                if ($operation === $this->getOperationCode()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function skipBySyncId(ResourceInterface $resource, SynchronizationInterface $synchronization): bool
    {
        $syncable = $this->extractor->getSyncableEntity($resource, $this);

        return $syncable !== null && $syncable->getSyncId() !== $synchronization->getSyncId();
    }

    public function getPreparedSerializer(): Serializer
    {
        if (!isset($this->serializer)) {
            $this->initSerializer();
        }

        return $this->serializer;
    }

    private function initSerializer(): void
    {
        $encoders = [new JsonEncoder()];
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizers = [new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, $extractor)];
        $this->serializer = new Serializer($normalizers, $encoders);
    }
}
