<?php

namespace App\Controller\Admin;

use App\Entity\Sale;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ArchivedSaleCrudController extends SaleCrudController
{
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
          ->setEntityLabelInPlural("Ventas históricas")
          ->setEntityLabelInSingular("Venta histórica")
          ->setPageTitle(Crud::PAGE_INDEX, "Ventas históricas")
          ->setHelp(Crud::PAGE_INDEX, "Listado de todas las ventas.")
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('contingency', 'Contingencia'),
            AssociationField::new('createdBy', 'Registrado por')->setFieldFqcn(User::class),
            DateTimeField::new('quote.saleDate', 'Fecha/Hora'),
            TextField::new('quote.rut', 'RUT'),
            IntegerField::new('quote.amount', 'Total'),
            IntegerField::new('quote.installments', 'Plazo'),
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
