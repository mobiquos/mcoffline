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
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'doctrine' => '?'.EntityManagerInterface::class,
        ]);
    }

    public function index(): Response
    {
        $params = [
            'location_code' => null,
            'location' => null,
            'contingency' => null,
        ];
        $em = $this->container->get('doctrine');

        $locationCodeParam = $em->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_LOCATION_CODE]);
        if ($locationCodeParam) {
            $locationCode = $locationCodeParam->getValue();
            $params['location_code'] = $locationCode;

            $location = $em->getRepository(Location::class)->findOneBy(['code' => $locationCode]);
            if ($location) {
                $params['location'] = $location;
                $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null], ['id' => 'DESC']);
                $params['contingency'] = $contingency;

                if ($contingency) {
                    $report = $em->getRepository(Sale::class)->getContingencyReport($contingency);
                    $params['contingency_report'] = $report;
                }
            }
        }

        $lastSyncEvent = $em->getRepository(SyncEvent::class)->findOneBy(['status' => SyncEvent::STATUS_SUCCESS], ['createdAt' => 'DESC']);
        $params['last_sync_event'] = $lastSyncEvent;

        return $this->render('admin/index.html.twig', $params);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setLocales(['es'])
            ->setTitle('<img src="/images/logo-text.png">')
            ->setFaviconPath('/images/multicentro-favicon-32x32.png')
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        $em = $this->container->get('doctrine');
        $locationCode = $em->getRepository(SystemParameter::class)->find(SystemParameter::PARAM_LOCATION_CODE);
        $location = $em->getRepository(Location::class)->findOneBy(['code' => $locationCode]);

        if ($location) {
            $contingency = $em->getRepository(Contingency::class)->findOneBy(['location' => current($location), 'endedAt' => null, ['id' => 'DESC']]);
            if (current($contingency)) {
            }
        }
        yield MenuItem::linkToRoute('Ir a herramientas', 'fas fa-tool', 'home');

        yield MenuItem::section("Información contingencia");
        yield MenuItem::linkToCrud('Cotizaciones', 'fas fa-dollar', Quote::class);
        yield MenuItem::linkToCrud('Ventas', 'fas fa-dollar', Sale::class);
        yield MenuItem::linkToCrud('Pagos', 'fas fa-money-bill', Payment::class);

        yield MenuItem::section("Historial");
        yield MenuItem::linkToCrud('Ventas históricas', 'fas fa-archive', Sale::class)->setController(ArchivedSaleCrudController::class)->setAction(Action::INDEX);

        yield MenuItem::section("Clientes");
        yield MenuItem::linkToCrud('Clientes', 'fas fa-user', Client::class);

        yield MenuItem::section("Sistema");
        yield MenuItem::linkToCrud('Contingencias', 'bi bi-alarm', Contingency::class);
        yield MenuItem::linkToCrud('Historial de sincronización', 'bi bi-sync', SyncEvent::class);
        yield MenuItem::linkToCrud('Usuarios', 'fas fa-users', User::class);
        yield MenuItem::linkToCrud('Locales', 'fas fa-building', Location::class);
        yield MenuItem::linkToCrud('Dispositivos', 'fas fa-device', Device::class);
        yield MenuItem::linkToRoute('Parámetros de sistema', 'fas fa-gears', 'admin_system_parameter_config');
        yield MenuItem::linkToCrud('Manual de usuario', 'fas fa-book', SystemDocument::class);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addAssetMapperEntry("app");
    }
}
