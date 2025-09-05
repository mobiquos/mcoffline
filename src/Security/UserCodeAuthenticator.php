<?php
namespace App\Security;

use App\Entity\Contingency;
use App\Entity\Location;
use App\Entity\SystemParameter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
    private EntityManagerInterface $entityManager;

    public function __construct(UrlGeneratorInterface $urlGenerator, UserRepository $userRepository, EntityManagerInterface $em)
    {
        $this->entityManager = $em;
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

                if (!$user->isEnabled()) {
                    throw new CustomUserMessageAuthenticationException('El usuario no está habilitado para iniciar sesión.');
                }


                if (!(in_array(User::ROLE_LOCATION_ADMIN, $user->getRoles(), true) or in_array(User::ROLE_ADMIN, $user->getRoles(), true) or in_array(User::ROLE_SUPER_ADMIN, $user->getRoles(), true))) {
                    if (in_array(User::ROLE_USER, $user->getRoles(), true)) {
                        $locationCode = $this->entityManager->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_LOCATION_CODE]);
                        if (!$locationCode) {
                            throw new CustomUserMessageAuthenticationException('La configuración del sistema está incompleta.');
                        }

                        $location = $this->entityManager->getRepository(Location::class)->findOneBy(['code' => (string) $locationCode->getValue()]);
                        if (!$location) {
                            throw new CustomUserMessageAuthenticationException('El local indicado en la configuración no corresponde a uno de los locales registrados.');
                        }

                        if (!$location->isEnabled()) {
                            throw new CustomUserMessageAuthenticationException('Este local está desactivado.');
                        }
                        if (!$user->getLocation()) {
                            throw new CustomUserMessageAuthenticationException('Este usuario no tiene una tienda asignada.');
                        }

                        if ($user->getLocation()->getId() !== $location->getId()) {
                            throw new CustomUserMessageAuthenticationException('El usuario no pertenece a esta tienda.');
                        }

                        $activeContingency = $this->entityManager->getRepository(Contingency::class)->findOneBy(['location' => $location, 'endedAt' => null], ['id' => 'DESC']);
                        if (!$activeContingency) {
                            throw new CustomUserMessageAuthenticationException('El inicio de sesión para este usuario solo está permitido durante un período de contingencia activo.');
                        }
                    }
                }

                return new CodeAuthenticatedUser($user, array_merge(['ROLE_CODE_AUTH'], $user->getRoles()));
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token, string $firewallName): ?Response
    {
        $sessionLifetime = $this->entityManager->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_SESSION_LIFETIME]);
        $minutes = ((int) $sessionLifetime->getValue() ?? 10);
        if ($sessionLifetime) {
            $request->getSession()->migrate(false, (int) $minutes * 60);
        }

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

