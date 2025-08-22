<?php

// src/EventSubscriber/DowngradeSubscriber.php
namespace App\EventSubscriber;

use App\Security\CodeAuthenticatedUser;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserDowngradeSubscriber implements EventSubscriberInterface
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $token   = $this->tokenStorage->getToken();

        if (!$token) {
            return;
        }

        $user = $token->getUser();

        // If already downgraded, nothing to do
        if ($user instanceof CodeAuthenticatedUser) {
            return;
        }

        // Only act if the user is the full User entity
        if ($user instanceof User) {
            $path = $request->getPathInfo();

            // if user is OUTSIDE secure area, downgrade
            if (!str_starts_with($path, '/admin')) {
                $downgradedUser = new CodeAuthenticatedUser($user, array_merge(['ROLE_CODE_AUTH'], $user->getRoles()));

                $newToken = new UsernamePasswordToken(
                    $downgradedUser,
                    'contingency',
                    $downgradedUser->getRoles(),
                );

                $this->tokenStorage->setToken($newToken);
                $request->getSession()->set('_security_main', serialize($newToken));
            }
        }
    }
}
