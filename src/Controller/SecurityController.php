<?php

namespace App\Controller;

use App\Entity\Contingency;
use App\Repository\SyncEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/secure/login', name: 'backoffice_login')]
    public function login(AuthenticationUtils $authenticationUtils, SyncEventRepository $syncEventRepository): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'last_sync' => $syncEventRepository->findLastSuccessful(),
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException(
            'This method can be blank - it will be intercepted by the logout key on your firewall.'
        );
    }

    #[Route(path: '/', name: 'index')]
    public function index(): Response
    {
        return $this->redirectToRoute('home');
    }

    #[Route(path: '/secure/home', name: 'home')]
    public function home(EntityManagerInterface $em): Response
    {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/pdfs';
        $filepath = $uploadDir . '/manual.pdf';
        $pdfExists = file_exists($filepath);

        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);

        return $this->render('index.html.twig', [
            'pdfExists' => $pdfExists,
            'contingency' => $contingency,
        ]);
    }
}
