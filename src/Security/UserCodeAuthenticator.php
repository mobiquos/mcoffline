<?php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use App\Repository\UserRepository;

class UserCodeAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'app_contingency_login';

    private UrlGeneratorInterface $urlGenerator;
    private UserRepository $userRepository;

    public function __construct(UrlGeneratorInterface $urlGenerator, UserRepository $userRepository)
    {
        $this->urlGenerator = $urlGenerator;
        $this->userRepository = $userRepository;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $code = $request->request->get('code');

        if (!$code) {
            throw new CustomUserMessageAuthenticationException('Código de usuario requerido.');
        }

        return new SelfValidatingPassport(
            new UserBadge($code, function (string $code) {
                $user = $this->userRepository->findOneBy(['code' => $code]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Usuario no encontrado.');
                }

                return new CodeAuthenticatedUser($user, array_merge(['ROLE_CODE_AUTH'], $user->getRoles()));
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        // redirect somewhere after code login (basic access)
        return new \Symfony\Component\HttpFoundation\RedirectResponse(
            $this->urlGenerator->generate('home')
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

