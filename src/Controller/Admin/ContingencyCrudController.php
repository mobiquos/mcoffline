<?php

namespace App\Controller\Admin;

use App\Entity\Contingency;
use App\Entity\Location;
use App\Entity\Payment;
use App\Entity\Sale;
use App\Entity\SystemParameter;
use App\Entity\User;
use App\Security\CodeAuthenticatedUser;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Entity\SyncEvent;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class ContingencyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Contingency::class;
    }

    #[AdminAction(routeName: 'open_close', routePath: '/current')]
    public function openClose(): Response
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

        return $this->render('location_admin/index.html.twig', $params);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural("Contingencias")
            ->setEntityLabelInSingular("Contingencia")
            ->setPageTitle(Crud::PAGE_NEW, "Iniciar contingencia")
            ->setDefaultSort(['id' => 'DESC'])
            ->setHelp(Crud::PAGE_INDEX, "Listado de contingencias.");
    }

    public function new(AdminContext $context)
    {
        $em = $this->container->get('doctrine')->getManager();
        $maxSyncAgeInDays = $em->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_MAX_SYNC_AGE_IN_DAYS]);
        $lastSyncEvent = $em->getRepository(SyncEvent::class)->findOneBy(['status' => SyncEvent::STATUS_SUCCESS], ['createdAt' => 'DESC']);

        if ($lastSyncEvent) {
            $syncAge = $lastSyncEvent->getCreatedAt()->diff(new \DateTime())->days;
            if ($syncAge > (int)$maxSyncAgeInDays->getValue()) {
                $this->addFlash('danger', sprintf('La última sincronización fue hace %d días. No se puede iniciar una contingencia.', $syncAge));
                $url = $this->container->get(AdminUrlGenerator::class)->setController(ContingencyCrudController::class)->setAction('openClose')->generateUrl();
                return $this->redirect($url);
            }
        }

        return parent::new($context);
    }

    public function createEntity(string $entityFqcn)
    {
        $contingency = new Contingency();
        $contingency->setStartedAt(new \DateTime());

        if ($this->getUser() instanceof CodeAuthenticatedUser) {
            /** @var UserRepository */
            $userRepository = $this->container->get('doctrine')->getRepository(User::class);
            $user = $userRepository->find($this->getUser()->getOriginalUser()->getId());
            $contingency->setStartedBy($user);
        } else {
            $contingency->setStartedBy($this->getUser());
        }

        $locationCode = $this->container->get('doctrine')->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_LOCATION_CODE]);
        if ($locationCode) {
            $contingency->setLocationCode($locationCode->getValue());
            $location = $this->container->get('doctrine')->getRepository(Location::class)->findOneBy(['code' => $locationCode->getValue()]);
            $contingency->setLocation($location);
        }
        return $contingency;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', "ID")->setDisabled(),
            AssociationField::new('location', "Local")->setDisabled(),
            DateTimeField::new('startedAt', "Inicio")->hideWhenUpdating(),
            DateTimeField::new('endedAt', "Termino")->hideWhenCreating(),
            AssociationField::new('startedBy', "Iniciada por")->hideOnForm()->setDisabled(),
            IntegerField::new('salesQuantity', 'Ventas')->onlyOnIndex(),
            IntegerField::new('salesTotalAmount', 'Monto Ventas')->onlyOnIndex(),
            IntegerField::new('paymentsQuantity', 'Pagos')->onlyOnIndex(),
            IntegerField::new('paymentsTotalAmount', 'Monto Pagos')->onlyOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $reopen = Action::new('reopen', 'Reabrir', 'fa fa-door-open')
            ->linkToCrudAction('reopenContingency')
            ->displayIf(static function ($entity) {
                return $entity->getEndedAt() !== null;
            });

        $exportSales = Action::new('export_sales', 'Exportar Ventas', 'fa fa-file-csv')
            ->linkToCrudAction('exportSales');

        $exportPayments = Action::new('export_payments', 'Exportar Pagos', 'fa fa-file-csv')
            ->linkToCrudAction('exportPayments');

        $exportIndex = Action::new('export_index', 'Exportar', 'fa fa-file-csv')
            ->linkToCrudAction('exportIndex')
            ->createAsGlobalAction();

        return $actions->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->add(Crud::PAGE_INDEX, $reopen)
            ->add(Crud::PAGE_INDEX, $exportSales)
            ->add(Crud::PAGE_INDEX, $exportPayments)
            ->add(Crud::PAGE_INDEX, $exportIndex)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::DELETE);
    }

    #[AdminAction(routeName: 'export_index', routePath: '/index/csv')]
    public function exportIndex(AdminContext $context, EntityManagerInterface $em): StreamedResponse
    {
        $contingencies = $em->getRepository(Contingency::class)->findAll();

        $response = new StreamedResponse(function () use ($contingencies) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, [
                'ID',
                'Local',
                'Inicio',
                'Termino',
                'Iniciada por',
                'Ventas',
                'Monto Ventas',
                'Pagos',
                'Monto Pagos'
            ]);

            foreach ($contingencies as $contingency) {
                fputcsv($handle, [
                    $contingency->getId(),
                    $contingency->getLocation(),
                    $contingency->getStartedAt()->format('Y-m-d H:i:s'),
                    $contingency->getEndedAt() ? $contingency->getEndedAt()->format('Y-m-d H:i:s') : '',
                    $contingency->getStartedBy(),
                    $contingency->getSalesQuantity(),
                    $contingency->getSalesTotalAmount(),
                    $contingency->getPaymentsQuantity(),
                    $contingency->getPaymentsTotalAmount(),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="contingencies.csv"');

        return $response;
    }

    #[AdminAction(routeName: 'export_sales', routePath: '/{entityId}/sales/csv')]
    public function exportSales(AdminContext $context, EntityManagerInterface $em): StreamedResponse
    {
        $contingency = $context->getEntity()->getInstance();
        $sales = $em->getRepository(Sale::class)->findBy(['contingency' => $contingency]);

        $response = new StreamedResponse(function () use ($sales, $contingency) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, [
                'ID Contingencia',
                'ID Transacción de Venta',
                'Fecha/Hora Transacción',
                'Código Local',
                'N Documento',
                'RUT',
                'Monto a Financiar',
                'N Cuotas',
                'Tasa de Interés',
                'Primer Vencimiento',
                'Valor Cuota',
                'Costo Total Cŕedito',
                'Vendedor',
                'Cajero'
            ]);

            foreach ($sales as $sale) {
                $quote = $sale->getQuote();
                $nextBillingDate = clone $sale->getCreatedAt();
                $nextBillingDate->modify('+1 month');

                fputcsv($handle, [
                    $contingency->getId(),
                    $sale->getId(),
                    $sale->getCreatedAt()->format('Y-m-d H:i:s'),
                    $contingency->getLocationCode(),
                    $sale->getFolio(),
                    $sale->getRut(),
                    $quote->getAmount(),
                    $quote->getInstallments(),
                    $quote->getInterest(),
                    $nextBillingDate->format('Y-m-d'),
                    $quote->getInstallmentAmount(),
                    $quote->getTotalAmount(),
                    $quote->getCreatedBy() ? $quote->getCreatedBy()->getFullName() : '',
                    $sale->getCreatedBy() ? $sale->getCreatedBy()->getFullName() : ''
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="contingency_sales.csv"');

        return $response;
    }

    #[AdminAction(routeName: 'export_payments', routePath: '/{entityId}/payments/csv')]
    public function exportPayments(AdminContext $context, EntityManagerInterface $em): StreamedResponse
    {
        $contingency = $context->getEntity()->getInstance();
        $payments = $em->getRepository(Payment::class)->findBy(['contingency' => $contingency]);

        $response = new StreamedResponse(function () use ($payments, $contingency) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, [
                'ID Contingencia',
                'ID Transacción Pago',
                'Fecha/Hora Transacción',
                'Código Local',
                'Monto Abonado',
                'Medio de Pago',
                'ID Voucher',
                'Cajero'
            ]);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $contingency->getId(),
                    $payment->getId(),
                    $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                    $contingency->getLocationCode(),
                    $payment->getAmount(),
                    $payment->getPaymentMethod(),
                    $payment->getVoucherId(),
                    $payment->getCreatedBy() ? $payment->getCreatedBy()->getFullName() : ''
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="contingency_payments.csv"');

        return $response;
    }

    public function reopenContingency(AdminContext $context): RedirectResponse
    {
        $em = $this->container->get('doctrine')->getManager();
        $activeContingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);

        if ($activeContingency) {
            $this->addFlash('danger', 'Ya existe una contingencia activa. No se puede reabrir otra.');
        } else {
            $contingency = $context->getEntity()->getInstance();
            $contingency->setEndedAt(null);

            $this->updateEntity($em, $contingency);
            $this->addFlash('success', 'Contingencia reabierta.');
        }

        $url = $this->container->get(AdminUrlGenerator::class)
            ->setController(ContingencyCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function close(AdminContext $context): RedirectResponse
    {
        $em = $this->container->get('doctrine')->getManager();
        $activeContingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);

        if ($activeContingency) {
            $activeContingency->setEndedAt(new \DateTime());

            $this->updateEntity($em, $activeContingency);
            $this->addFlash('success', 'Contingencia cerrada.');
        }

        $url = $this->container->get(AdminUrlGenerator::class)
            ->setController(ContingencyCrudController::class)
            ->setAction('openClose')
            ->generateUrl();

        return $this->redirect($url);
    }
}
