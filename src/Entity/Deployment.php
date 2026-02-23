<?php

namespace App\Entity;

use App\Repository\DeploymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeploymentRepository::class)]
#[ORM\Table(name: 'deployment')]
#[ORM\UniqueConstraint(name: 'unique_pim_extension', columns: ['pim_url', 'extension_slug'])]
class Deployment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DEPLOYED = 'deployed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DELETED = 'deleted';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $extensionSlug;

    #[ORM\Column(length: 500)]
    private ?string $pimUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $remoteUuid = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExtensionSlug(): string
    {
        return $this->extensionSlug;
    }

    public function setExtensionSlug(string $extensionSlug): static
    {
        $this->extensionSlug = $extensionSlug;
        return $this;
    }

    public function getPimUrl(): ?string
    {
        return $this->pimUrl;
    }

    public function setPimUrl(string $pimUrl): static
    {
        $this->pimUrl = $pimUrl;
        return $this;
    }

    public function getRemoteUuid(): ?string
    {
        return $this->remoteUuid;
    }

    public function setRemoteUuid(?string $remoteUuid): static
    {
        $this->remoteUuid = $remoteUuid;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
