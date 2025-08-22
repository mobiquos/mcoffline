<?php

// src/Security/CodeUserProvider.php
namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use App\Entity\User;

class CodeUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // This will be called only if you use UserBadge without a closure
        // In our case, CodeAuthenticator uses a closure to fetch user, so not needed
        throw new \Exception('Direct loading not supported for CodeUserProvider.');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        // If it's our wrapper, keep it as-is (no DB reload needed)
        if ($user instanceof CodeAuthenticatedUser) {
            return $user;
        }

        // If it's the real User entity, allow normal refresh
        if ($user instanceof User) {
            return $user;
        }

        throw new \InvalidArgumentException(sprintf(
            'Unsupported user type "%s".',
            get_class($user)
        ));
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || $class === CodeAuthenticatedUser::class;
    }
}

