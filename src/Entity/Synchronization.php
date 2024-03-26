<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @codeCoverageIgnore
 */
#[ORM\Entity()]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'sylius_gtt_synchronization')]
#[UniqueEntity('operationId')]
class Synchronization implements SynchronizationInterface, TimestampableInterface
{
    use TimestampableTrait;

    // @codingStandardsIgnoreStart

    /**
     * @var DateTimeImmutable|null $createdAt
     */
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected $createdAt = null;

    /**
     * @var DateTimeImmutable|null $updatedAt
     */
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected $updatedAt = null;

    // @codingStandardsIgnoreEnd

    #[ORM\Id()]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue()]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $type;

    #[ORM\Column(name: 'flow_type', type: Types::STRING, length: 255, nullable: false)]
    private string $flowType;

    #[ORM\Column(name: 'operation_code', type: Types::STRING, length: 255, nullable: false)]
    private ?string $operationCode = null;

    #[ORM\Column(name: 'sync_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $syncId = null;

    #[ORM\Column(name: 'operation_id', type: Types::GUID, nullable: false)]
    private ?string $operationId = null;

    /**
     * @var array<string, array<int|string|bool|float|array|null>> $payload
     */
    #[ORM\Column(type: Types::JSON, nullable: false)]
    private array $payload = [];

    #[ORM\Column(type: Types::STRING, nullable: false)]
    private string $status = self::STATUS_BEFORE_SYNC;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getFlowType(): string
    {
        return $this->flowType;
    }

    public function setFlowType(string $flowType): void
    {
        $this->flowType = $flowType;
    }

    public function getOperationCode(): ?string
    {
        return $this->operationCode;
    }

    public function setOperationCode(?string $operationCode): void
    {
        $this->operationCode = $operationCode;
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function setOperationId(?string $operationId): void
    {
        $this->operationId = $operationId;
    }

    public function getSyncId(): ?string
    {
        return $this->syncId;
    }

    public function setSyncId(?string $syncId): void
    {
        $this->syncId = $syncId;
    }

    /**
     * @return array<string, array<int|string|bool|float|array|null>>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, array<int|string|bool|float|array|null>> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    #[ORM\PreFlush]
    public function preFlush(): void
    {
        $this->setUpdatedAt(new DateTimeImmutable());

        if ($this->getCreatedAt() === null) {
            $this->setCreatedAt(new DateTimeImmutable());
        }

        if ($this->getOperationId() === null) {
            $this->setOperationId(Uuid::uuid4()->toString());
        }
    }
}
