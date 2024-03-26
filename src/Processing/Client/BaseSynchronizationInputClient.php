<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Processing\Client;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use Gtt\SynchronizationPlugin\Api\Input\ReceiveOperation;
use Gtt\SynchronizationPlugin\DoctrineService\SynchronizationAttributeDataExtractor;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableInputInterface;
use Gtt\SynchronizationPlugin\DTO\Contract\TransferableItemInterface;
use Gtt\SynchronizationPlugin\Entity\SynchronizationInterface;
use Gtt\SynchronizationPlugin\Exception\SynchronizationCorruptedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

abstract class BaseSynchronizationInputClient implements SynchronizationInputClientInterface
{
    public const CODE = '';

    public const TYPE = '';

    public const CRUD_TYPES = SynchronizationClientInterface::CRUD_TYPES_ALL;

    protected Serializer $serializer;

    abstract public function getTransferEnvelopDtoClass(): string;

    abstract public function getTransferItemDtoClass(): string;

    abstract public function synchronizeInput(
        SynchronizationInterface $synchronization,
        TransferableItemInterface $item
    ): void;

    public function __construct(
        protected ClientInterface $httpClient,
        protected SynchronizationAttributeDataExtractor $extractor,
        protected EntityManagerInterface $entityManager,
        protected LoggerInterface $logger,
    ) {
        $this->initSerializer();
    }

    public function getPreparedSerializer(): Serializer
    {
        if (!isset($this->serializer)) {
            $this->initSerializer();
        }

        return $this->serializer;
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

    public function isSupported(ReceiveOperation $receiveOperation): bool
    {
        return $this->getOperationCode() === $receiveOperation->getOperationCode();
    }

    public function buildTransferableInput(ReceiveOperation $receiveOperation): ?TransferableInputInterface
    {
        $envelopClass = $this->getTransferEnvelopDtoClass();

        $itemClass = $this->getTransferItemDtoClass();

        $input = new $envelopClass(
            $receiveOperation->getOperationId(),
            $receiveOperation->getOperationCode(),
            []
        );

        assert($input instanceof TransferableInputInterface);

        $data = $this->serializer->denormalize(
            $receiveOperation->getData(),
            $itemClass . '[]',
            null,
            [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [$itemClass => ['syncId' => '']],
            ],
        );

        $input->setData($data);

        return $input;
    }

    /**
     * PhpDocExtractor to follow PHP anotations of properties and parameters types,
     * ReflectionExtractor to read those from classes structure.
     *
     * Those are used to reconstruct objects from input data.
     * @see https://symfony.com/doc/current/components/serializer.html
     */
    protected function initSerializer(): void
    {
        $encoders = [new JsonEncoder()];
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizers = [new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, $extractor)];
        $this->serializer = new Serializer($normalizers, $encoders);
    }
}
