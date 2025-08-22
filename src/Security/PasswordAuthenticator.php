<?php

// src/Security/PasswordAuthenticator.php
namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class PasswordAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'backoffice_login';

    private UrlGeneratorInterface $urlGenerator;
    private UserRepository $userRepository;
    private TokenStorageInterface $tokenStorage;

    public function __construct(UrlGeneratorInterface $urlGenerator, UserRepository $userRepository, TokenStorageInterface $tokenStorage)
    {
        $this->urlGenerator = $urlGenerator;
        $this->userRepository = $userRepository;
        $this->tokenStorage = $tokenStorage;
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');

        return new Passport(
            new UserBadge($username, function (string $username) {
                $user = $this->userRepository->findOneBy(['rut' => $username]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Usuario no encontrado.');
                }

                if (!(in_array(User::ROLE_LOCATION_ADMIN, $user->getRoles()) or in_array(User::ROLE_ADMIN, $user->getRoles()) or in_array(User::ROLE_SUPER_ADMIN, $user->getRoles()))) {
                    throw new CustomUserMessageAuthenticationException('Ingreso no permitido.');
                }
                return $user;
            }),
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        $newToken = new UsernamePasswordToken(
            $user,
            'backoffice',
            $user->getRoles()
        );

        $this->tokenStorage->setToken($newToken);
        $request->getSession()->set('_security_main', serialize($newToken));

        // redirect to secure area
        return new \Symfony\Component\HttpFoundation\RedirectResponse(
            $this->urlGenerator->generate('admin')
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

