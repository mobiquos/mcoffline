<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Payment;
use App\Entity\Quote;
use App\Entity\SystemParameter;
use App\Entity\User;
use App\Filter\DateFilter;
use App\Service\PrintVoucherService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentMainCrudController extends AbstractCrudController
{
    private PrintVoucherService $printVoucherService;

    public function __construct(PrintVoucherService $printVoucherService)
    {
        $this->printVoucherService = $printVoucherService;
    }

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(DateFilter::new('createdAt', 'Fecha de registro'))
            ->add(EntityFilter::new('contingency', 'Contingencia'))
            ->add(TextFilter::new('rut', 'RUT cliente'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Pagos contingencia')
            ->setEntityLabelInSingular('Pago')
            ->setDefaultSort(['id' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'Pagos')
            ->setHelp(Crud::PAGE_INDEX, 'Listado de pagos registrados en el sistema.');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            DateTimeField::new('createdAt', 'Fecha')->formatValue(fn ($d) => $d->format("d-m-Y"))->onlyOnIndex(),
            TextField::new('contingency.location.code', 'Agencia')->onlyOnIndex(),
            TextField::new('rut', 'RUT Cliente')->setDisabled(true),
            IntegerField::new('amount', 'Monto pago')->setDisabled(true),
            TextField::new('paymentMethod', 'Medio pago')->setTemplatePath('admin/field/payment_method.html.twig'),
            TextField::new('voucherId', 'Comprobante Externo'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'Exportar', 'fa fa-download')
            ->linkToCrudAction('exportCsv')
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        $showVoucherAction = Action::new('showVoucher', 'Ver Voucher', 'fa fa-file-text-o')
            ->linkToCrudAction('showVoucher');

        $reprintVoucherAction = Action::new('reprintVoucher', 'Reimprimir Voucher', 'fa fa-print')
            ->linkToCrudAction('reprintVoucher');

        return $actions
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->add(Crud::PAGE_INDEX, $showVoucherAction)
            ->add(Crud::PAGE_INDEX, $reprintVoucherAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) => $action->displayIf(fn($entity) => $entity->getCreatedAt()->format("Ymd") == (new \DateTime)->format("Ymd")))
            ->setPermission('showVoucher', User::ROLE_SUPER_ADMIN)
            ->setPermission('reprintVoucher', User::ROLE_SUPER_ADMIN)
            ->disable(Action::DELETE, Action::NEW);
    }

    #[AdminAction(routeName: 'export_csv', routePath: '/export/csv')]
    public function exportCsv(AdminContext $context, EntityManagerInterface $em): StreamedResponse
    {
        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);
        $payments = $queryBuilder->getQuery()->getResult();

        $payments = $queryBuilder->getQuery()->getResult();

        $response = new StreamedResponse(function () use ($payments) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['Fecha', 'Agencia', 'RUT Cliente', 'Monto Pago', 'Medio Pago', 'Comprobante Externo'], ';');

            foreach ($payments as $q) {
                fputcsv($handle, [
                    $q->getCreatedAt()->format('d-m-Y'),
                    $q->getContingency()->getLocation()->getCode(),
                    $q->getRut(),
                    $q->getAmount(),
                    $q->getPaymentMethod(),
                    $q->getVoucherId(),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="Contingencia_Pagos_Compania.csv"'));

        return $response;
    }

    #[AdminAction(routeName: 'show_voucher', routePath: '/{entityId}/voucher')]
    public function showVoucher(AdminContext $context): Response
    {
        $payment = $context->getEntity()->getInstance();

        return $this->render('admin/voucher.html.twig', [
            'voucher_content' => $payment->getVoucherContent(),
        ]);
    }

    #[AdminAction(routeName: 'reprint_voucher', routePath: '/{entityId}/reprint-voucher')]
    public function reprintVoucher(AdminContext $context): Response
    {
        $payment = $context->getEntity()->getInstance();

        try {
            // Print the voucher using the service
            $success = $this->printVoucherService->printPaymentVoucher($payment);

            if ($success) {
                $this->addFlash('success', 'Voucher reimprimido exitosamente.');
            } else {
                $this->addFlash('error', 'Error al reimprimir el voucher.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al reimprimir el voucher: ' . $e->getMessage());
        }

        return $this->redirect($context->getReferrerUrl() ?? $this->generateUrl('admin'));
    }
}
