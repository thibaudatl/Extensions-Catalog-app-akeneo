<?php

namespace App\Entity;

use App\Repository\PimTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PimTokenRepository::class)]
#[ORM\Table(name: 'pim_token')]
class PimToken
{
    #[ORM\Id]
    #[ORM\Column(length: 500)]
    private string $pimUrl;

    #[ORM\Column(type: 'encrypted_string', length: 1024)]
    private string $accessToken;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $pimUrl, string $accessToken)
    {
        $this->pimUrl = $pimUrl;
        $this->accessToken = $accessToken;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPimUrl(): string
    {
        return $this->pimUrl;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
