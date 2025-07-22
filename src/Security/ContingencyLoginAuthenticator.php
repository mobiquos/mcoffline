<?php

namespace App\Security;

use App\Entity\Contingency;
use App\Entity\Location;
use App\Entity\SystemParameter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
// use Symfony\Component\Security\Core\Security;
use Symfony\Bundle\SecurityBundle\Security;

class ContingencyLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private UrlGeneratorInterface $urlGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager)
    {
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $request->getSession()->set('last_username', $username);

        $passport = new Passport(
            new UserBadge($username, function (string $userIdentifier) {
                return $this->entityManager->getRepository(User::class)->findOneBy(['rut' => $userIdentifier]);
            }),
            new PasswordCredentials($request->request->get('_password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );

        $user = $passport->getUser();

        if (!(in_array(User::ROLE_ADMIN, $user->getRoles(), true) or in_array(User::ROLE_SUPER_USER, $user->getRoles(), true))) {
            if (in_array('ROLE_USER', $user->getRoles(), true)) {

                $locationCode = $this->entityManager->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_LOCATION_CODE]);
                if (!$locationCode) {
                    throw new CustomUserMessageAuthenticationException(
                        'La configuración del sistema está incompleta.'
                    );
                }
                $location = $this->entityManager->getRepository(Location::class)->findOneBy(['code' => (string) $locationCode->getValue()]);
                if ($location) {
                    throw new CustomUserMessageAuthenticationException(
                        'El local indicado en la configuración no corresponde a uno de los locales registrados.'
                    );
                }

                $activeContingency = $this->entityManager->getRepository(Contingency::class)->findOneBy(['location' => $location, 'endedAt' => null], ['id' => 'DESC']);
                if (!$activeContingency) {
                    throw new CustomUserMessageAuthenticationException(
                        'El inicio de sesión para este usuario solo está permitido durante un período de contingencia activo.'
                    );
                }
            }
        }

        return $passport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $sessionLifetime = $this->entityManager->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_SESSION_LIFETIME]);
        $minutes = ((int) $sessionLifetime->getValue()) || 10;
        if ($sessionLifetime) {
            $request->getSession()->migrate(false, (int) $minutes * 60);
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
