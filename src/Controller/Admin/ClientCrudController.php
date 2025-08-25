<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\SyncEvent as AppSyncEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ClientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Client::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $em = $this->container->get('doctrine');
        $last = $em->getRepository(AppSyncEvent::class)->findLastSuccessful();
        if ($last) {
            $date = $last->getCreatedAt()->format('d/m/Y H:i');
        } else {
            $date = "-";
        }



       return $crud
          ->setEntityLabelInPlural("Clientes")
          ->setEntityLabelInSingular("Cliente")
          ->setPageTitle(Crud::PAGE_NEW, "Registrar nuevo cliente")
            ->setPageTitle(Crud::PAGE_INDEX, sprintf("Listado de clientes. (Actualizado al día %s hrs)", $date))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('rut', 'RUT'),
            TextField::new('name', 'Nombres'),
            TextField::new('firstLastName', 'Apellido paterno'),
            TextField::new('secondLastName', 'Apellido materno'),
            IntegerField::new('creditLimit', 'Cupo total'),
            IntegerField::new('creditAvailable', 'Cupo disponible'),
            IntegerField::new('originalCreditAvailable', 'Cupo disponible original'),
            TextField::new('blockComment', 'Bloqueos'),
            IntegerField::new('overdue', 'S. Vencido'),
            DateField::new('nextBillingAt', 'F.Prox.Facturación'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
        ;
    }
}
