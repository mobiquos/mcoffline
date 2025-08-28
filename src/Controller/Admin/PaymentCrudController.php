<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Payment;
use App\Entity\Quote;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
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

class PaymentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(DateTimeFilter::new('createdAt', 'Fecha de registro')->setFormTypeOption('value_type', DateType::class))
            ->add(EntityFilter::new('contingency', 'Contingencia'))
            ->add(TextFilter::new('rut', 'RUT cliente'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Pagos contingencia')
            ->setEntityLabelInSingular('Pago')
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

        return $actions
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->add(Crud::PAGE_INDEX, $showVoucherAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) => $action->displayIf(fn($entity) => $entity->getCreatedAt()->format("Ymd") == (new \DateTime)->format("Ymd")))
            ->setPermission('showVoucher', User::ROLE_SUPER_ADMIN)
            ->disable(Action::DELETE, Action::NEW);
    }

    #[AdminAction(routeName: 'export_csv', routePath: '/export/csv')]
    public function exportCsv(AdminContext $context, EntityManagerInterface $em): StreamedResponse
    {
        $contingency = $em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $payments = $em->getRepository(Payment::class)->findBy(['contingency' => $contingency]);

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
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="Contingencia_Pagos_Local%s.csv"', $contingency->getLocation()->getCode()));

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
}
