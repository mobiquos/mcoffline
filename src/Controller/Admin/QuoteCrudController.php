<?php

namespace App\Controller\Admin;

use App\Entity\Contingency;
use App\Entity\Quote;
use App\Entity\Sale;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\StreamedResponse;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class QuoteCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Quote::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(DateTimeFilter::new('quoteDate', 'Fecha de simulación')->setFormTypeOption('value_type', DateType::class))
            ->add(EntityFilter::new('contingency', 'Contingencia'))
            ->add(TextFilter::new('rut', 'RUT cliente'))
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
          ->setEntityLabelInPlural("Simulaciones Crédito")
          ->setEntityLabelInSingular("Simulación")
            ->setDefaultSort(['id' => 'DESC'])
          ->setPageTitle(Crud::PAGE_NEW, "Registrar simulación")
            ->setHelp(Crud::PAGE_INDEX, "Listado de simulaciones realizadas.")
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('publicId', 'ID Simulación'),
            IdField::new('contingency.id', 'ID Contingencia'),
            TextField::new('contingency.location.code', 'Agencia'),
            TextField::new('rut', 'RUT cliente')->onlyOnForms(),
            DateTimeField::new('quoteDate', 'Fecha')->formatValue(fn ($d) => $d->format("d-m-Y")),
            AssociationField::new('createdBy', 'Vendedor')->setFieldFqcn(User::class),
            AssociationField::new('sale.createdBy', 'Cajero')->setFieldFqcn(User::class),
            TextField::new('state', 'Estado')->setTemplatePath('admin/field/quote_state.html.twig')->onlyOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'Exportar', 'fa fa-download')
            ->linkToCrudAction('exportCsv')
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_INDEX, $exportAction)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }

    #[AdminAction(routeName: 'export_csv', routePath: '/export/csv')]
    public function exportCsv(AdminContext $context): StreamedResponse
    {
        $contingency = $this->em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
        $quotes = $this->em->getRepository(Quote::class)->findBy(['contingency' => $contingency]);

        $response = new StreamedResponse(function () use ($quotes) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['ID Simulación', 'ID Contingencia', 'Código Local', 'Fecha', 'Vendedor', 'Cajero', 'Estado'], ';');

            foreach ($quotes as $q) {
                fputcsv($handle, [
                    $q->getPublicId(),
                    $q->getContingency()->getId(),
                    $q->getLocationCode(),
                    $q->getQuoteDate()->format('d-m-Y'),
                    $q->getCreatedBy() ? $q->getCreatedBy()->getFullName() : '',
                    $q->getSale() && $q->getSale()->getCreatedBy() ? $q->getSale()->getCreatedBy()->getFullName() : '',
                    $this->getQuoteState($q),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="Simulaciones_Credito.csv"');

        return $response;
    }

    private function getQuoteState(Quote $quote): string
    {
        if ($quote->getSale() !== null) {
            return 'Autorizada';
        }

        if ($quote->getQuoteDate()->format('Y-m-d') < (new \DateTime())->format('Y-m-d')) {
            return 'Rechazada';
        }

        return 'Pendiente';
    }
}
