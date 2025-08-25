<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Validator\Rut;
use Symfony\Component\Validator\Constraints\Length;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity('code')]
#[UniqueEntity('rut')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_SUPER_ADMIN = "ROLE_SUPER_ADMIN";
    public const ROLE_ADMIN = "ROLE_ADMIN";
    public const ROLE_LOCATION_ADMIN = "ROLE_LOCATION_ADMIN";
    public const ROLE_CASHIER = "ROLE_CASHIER";
    public const ROLE_SELLER = "ROLE_SELLER";
    public const ROLE_USER = "ROLE_USER";

    public const ROLES = [
      // "Administrador TI" => self::ROLE_SUPER_ADMIN,
      "Administrador" => self::ROLE_ADMIN,
      "Tesorero de tienda" => self::ROLE_LOCATION_ADMIN,
      "Cajero" => self::ROLE_CASHIER,
      "Vendedor" => self::ROLE_SELLER
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Rut]
    #[Length(max:9)]
    #[ORM\Column(length: 9, nullable: false, unique: true)]
    private ?string $rut = null;

    #[Length(max:255)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fullName = null;

    #[ORM\Column(length: 10, unique: true)]
    #[Length(min:3)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $password = null;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    public ?string $plainPassword = null;

    #[ORM\Column]
    private ?bool $enabled = true;

    #[ORM\ManyToOne]
    private ?Location $location = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->rut;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getRol(): string
    {
        return current($this->roles);
    }

    public function setRol(string $rol): static
    {
        $this->roles = [$rol, "ROLE_USER"];

        return $this;
    }

    public function getRolPretty(): string {
        return array_find_key(self::ROLES, fn($d) => $d == $this->getRol()) ?? "";
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        $this->plainPassword = null;
    }

    public function __toString(): string
    {
        return $this->fullName;
    }

    public function getRut(): ?string
    {
        return $this->rut;
    }

    public function setRut(string $r): static
    {
        $this->rut = str_replace(["-", "."], "", $r);

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }
}
