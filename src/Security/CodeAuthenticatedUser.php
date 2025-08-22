<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\User;

class CodeAuthenticatedUser implements UserInterface
{
    private User $user;
    private $roles = ["ROLE_CODE_AUTH"];

    public function __construct(User $user, array $roles)
    {
        $this->user = $user;
        $this->roles = $roles;
    }

    public function getRoles(): array
    {
        // Force only code role, ignore DB roles
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->user->getUserIdentifier();
    }

    public function eraseCredentials(): void
    {
        // nothing
    }

    // Optional: if you need to access original user
    public function getOriginalUser(): User
    {
        return $this->user;
    }

    public function __toString(): string
    {
        return (string) $this->user;
    }
}
