<?php

namespace App\Controller\Admin;

use App\Controller\Admin\ArchivedSaleCrudController;
use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Device;
use App\Entity\Payment;
use App\Entity\Quote;
use App\Entity\Sale;
use App\Entity\SyncEvent;
use App\Entity\SystemDocument;
use App\Entity\SystemParameter;
use App\Entity\User;
use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ColorScheme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'doctrine' => '?'.EntityManagerInterface::class,
            UrlGeneratorInterface::class => '?'.UrlGeneratorInterface::class,
        ]);
    }

    #[Route('/admin/sync-status', name: 'admin_sync_status')]
    public function dailySyncStatus(): Response
    {
        $em = $this->container->get('doctrine');
        $locations = $em->getRepository(Location::class)->findAll();
        $syncData = [];

        foreach ($locations as $location) {
            $today = new \DateTime();

            $qb = $em->getRepository(SyncEvent::class)->createQueryBuilder('s');
            $qb->where('s.location = :location')
                ->setParameter('location', $location)
                ->orderBy('s.id', 'DESC');

            $syncEvents = $qb->getQuery()->getResult();
            $syncData[$location->getName()] = $syncEvents;
        }

        return $this->render('admin/sync_status.html.twig', [
            'syncData' => $syncData,
        ]);
    }

    public function configureCrud(): Crud
    {
        return (Crud::new())->setEntityPermission(User::ROLE_USER);
    }

    #[Route('/admin/dashboard', name: 'admin_index')]
    public function adminIndex(): Response
    {
        $locations = $this->container->get('doctrine')->getRepository(Location::class)->findAll();
        $data = [];

        foreach ($locations as $location) {
            $lastSyncEvent = $this->container->get('doctrine')->getRepository(SyncEvent::class)->findOneBy(['location' => $location], ['createdAt' => 'DESC']);
            $deviceCount = $this->container->get('doctrine')->getRepository(Device::class)->count(['location' => $location]);
            $userCount = $this->container->get('doctrine')->getRepository(User::class)->count(['location' => $location]);

            $data[] = [
                'location' => $location,
                'lastSyncEvent' => $lastSyncEvent,
                'deviceCount' => $deviceCount,
                'userCount' => $userCount,
            ];
        }

        return $this->render('admin/index.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/admin/upload-pdf', name: 'admin_upload_pdf', methods: ['GET', 'POST'])]
    public function uploadPdf(Request $request): Response
    {
        // Handle file upload
        if ($request->isMethod('POST')) {
            // Get the uploaded file
            $uploadedFile = $request->files->get('pdf_file');

            if ($uploadedFile && $uploadedFile->isValid()) {
                // Check if it's a PDF file
                if ($uploadedFile->getMimeType() === 'application/pdf') {
                    // Define upload directory
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/pdfs';

                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    // Remove existing PDF file if it exists
                    $existingFile = $uploadDir . '/manual.pdf';
                    if (file_exists($existingFile)) {
                        unlink($existingFile);
                    }

                    // Move the new file with a fixed name
                    $uploadedFile->move($uploadDir, 'manual.pdf');

                    // Add flash message
                    $this->addFlash('success', 'Archivo PDF actualizado correctamente.');

                    // Redirect to prevent resubmission
                    return $this->redirectToRoute('admin_upload_pdf');
                } else {
                    $this->addFlash('error', 'El archivo debe ser un PDF.');
                }
            } else {
                $this->addFlash('error', 'Por favor seleccione un archivo válido.');
            }
        }

        // Check if PDF exists
        $pdfExists = false;
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/pdfs';
        $filepath = $uploadDir . '/manual.pdf';

        if (file_exists($filepath)) {
            $pdfExists = true;
        }

        return $this->render('admin/upload_pdf.html.twig', [
            'pdfExists' => $pdfExists,
        ]);
    }

    #[Route('/admin/download-pdf', name: 'admin_download_pdf')]
    public function downloadPdf(): Response
    {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/pdfs';
        $filepath = $uploadDir . '/manual.pdf';

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('Archivo no encontrado.');
        }

        return $this->file($filepath, 'manual.pdf');
    }

    public function index(): Response
    {
        if ($this->isGranted(User::ROLE_SUPER_ADMIN)) {
            return $this->redirectToRoute('admin_index');
        }

        if ($this->isGranted(User::ROLE_ADMIN)) {
            return $this->redirectToRoute('admin_index');
        }

        if ($this->isGranted(User::ROLE_LOCATION_ADMIN)) {
            return $this->redirectToRoute('admin_contingency_open_close');
        }

        return "ERROR";
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setLocales(['es'])
            ->setDefaultColorScheme(ColorScheme::LIGHT)
            ->setTitle('<img src="/images/logo-text.png">')
            ->setFaviconPath('/images/multicentro-favicon-32x32.png')
        ;
    }

    public function configureMenuItems(): iterable
    {

        /** @var AdminUrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->container->get(AdminUrlGenerator::class);
        /** @var UrlGeneratorInterface $urlGenerator2 */
        $urlGenerator2 = $this->container->get(UrlGeneratorInterface::class);
        $em = $this->container->get('doctrine');
        $locationCode = $em->getRepository(SystemParameter::class)->findByCode(SystemParameter::PARAM_LOCATION_CODE);
        $location = $em->getRepository(Location::class)->findOneBy(['code' => $locationCode]);
        $systemVersion = $em->getRepository(SystemParameter::class)->findByCode(SystemParameter::PARAM_VERSION_TYPE);

        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Abrir/Cerrar Contingencia', 'fas fa-tool', Contingency::class)->setAction('openClose');
        yield MenuItem::linkToUrl('Simuladores', 'fas fa-tool', $urlGenerator2->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL));

        yield MenuItem::section("Detalle contingencias");
        if ($systemVersion->getValue() == "main") {
            yield MenuItem::linkToCrud('Ventas Multicentro', 'fas fa-dollar', Sale::class);
            yield MenuItem::linkToCrud('Pagos Multicentro', 'fas fa-money-bill', Payment::class);
        } else {
            yield MenuItem::linkToCrud('Simulaciones de Crédito', 'fas fa-dollar', Quote::class)->setPermission('ROLE_ADMIN');
            yield MenuItem::linkToCrud('Ventas', 'fas fa-dollar', Sale::class);
            yield MenuItem::linkToCrud('Pagos', 'fas fa-money-bill', Payment::class);
        }
        // yield MenuItem::linkToCrud('Ventas Sysretail', 'fas fa-dollar', Sale::class)->setPermission('ROLE_ADMIN');
        // yield MenuItem::linkToCrud('Pagos Sysretail', 'fas fa-money-bill', Payment::class)->setPermission('ROLE_ADMIN');


        yield MenuItem::section("Reportes")->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Clientes', 'fas fa-user', Client::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Histórico Contingencia', 'fas fa-archive', Contingency::class)->setPermission('ROLE_ADMIN');

        yield MenuItem::section("Configuración")->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Sincronizaciones', 'fas fa-users', SyncEvent::class)->setAction(Action::INDEX)->setPermission(User::ROLE_SUPER_ADMIN);
        yield MenuItem::linkToCrud('Usuarios', 'fas fa-users', User::class)->setAction(Action::INDEX)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Locales', 'fas fa-building', Location::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Equipos', 'fas fa-device', Device::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToRoute('Parámetros de sistema', 'fas fa-gears', 'admin_system_parameter_config')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToRoute('Actualizar Manual', 'fas fa-file-pdf', 'admin_upload_pdf')->setPermission('ROLE_SUPER_ADMIN');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addAssetMapperEntry("app");
    }
}
