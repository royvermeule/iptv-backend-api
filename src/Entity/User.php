<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(name: 'password_hash', type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\Column(name: 'is_verified', type: 'boolean', options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(name: 'verification_token', type: 'string', length: 64, nullable: true)]
    private ?string $verificationToken;

    #[ORM\Column(name: 'verified_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $email,
        string $passwordHash,
        ?string $verificationToken = null,
    ) {
        $this->id = (string) Uuid::v4();
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->verificationToken = $verificationToken;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    public function verify(): void
    {
        $this->isVerified = true;
        $this->verifiedAt = new \DateTimeImmutable();
        $this->verificationToken = null;
    }
}
