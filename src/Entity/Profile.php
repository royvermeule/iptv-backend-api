<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: "profiles")]
class Profile
{
    #[ORM\Id]
    #[ORM\Column(type: "string", length: 36)]
    private string $id;

    #[ORM\Column(name: "user_id", type: "string", length: 36)]
    private string $userId;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[
        ORM\Column(
            name: "country_code",
            type: "string",
            length: 2,
            nullable: true,
        ),
    ]
    private ?string $countryCode;

    #[ORM\Column(name: "pin", type: "integer", length: 4, nullable: true)]
    private ?int $pin = null;

    #[
        ORM\Column(
            name: "xtream_url",
            type: "string",
            length: 500,
            nullable: true,
        ),
    ]
    private ?string $xtreamUrl = null;

    #[
        ORM\Column(
            name: "xtream_username",
            type: "string",
            length: 500,
            nullable: true,
        ),
    ]
    private ?string $xtreamUsername = null;

    #[
        ORM\Column(
            name: "xtream_password",
            type: "string",
            length: 500,
            nullable: true,
        ),
    ]
    private ?string $xtreamPassword = null;

    #[ORM\Column(name: "created_at", type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $userId,
        string $name,
        ?string $countryCode = null,
        ?int $pin = null,
    ) {
        $this->id = (string) Uuid::v4();
        $this->userId = $userId;
        $this->name = $name;
        $this->countryCode = $countryCode;
        $this->pin = $pin;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getPIn(): ?int
    {
        return $this->pin;
    }

    public function getXtreamUrl(): ?string
    {
        return $this->xtreamUrl;
    }

    public function getXtreamUsername(): ?string
    {
        return $this->xtreamUsername;
    }

    public function getXtreamPassword(): ?string
    {
        return $this->xtreamPassword;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setCountryCode(?string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    public function setPin(?int $pin): void
    {
        $this->pin = $pin;
    }

    public function setCredentials(
        string $url,
        string $username,
        string $password,
    ): void {
        $this->xtreamUrl = $url;
        $this->xtreamUsername = $username;
        $this->xtreamPassword = $password;
    }

    public function hasCredentials(): bool
    {
        return $this->xtreamUrl !== null;
    }
}
