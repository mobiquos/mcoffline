<?php

namespace App\Controller\Admin;

use App\Entity\Device;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeviceCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return Device::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'Exportar a CSV')
            ->setIcon('fa fa-file-csv')
            ->linkToCrudAction('exportDevices')
            ->setCssClass('btn btn-success')
            ->createAsGlobalAction();

        return $actions->add(Crud::PAGE_INDEX, $exportAction);
    }

    public function exportDevices(Request $request): StreamedResponse
    {
        $devices = $this->entityManager->getRepository(Device::class)->findAll();

        $response = new StreamedResponse(function () use ($devices) {
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, ['ID', 'Local', 'Dirección IP', 'Nombre POS', 'Número POS', 'Habilitado']);

            foreach ($devices as $device) {
                fputcsv($handle, [
                    $device->getId(),
                    $device->getLocation() ? $device->getLocation()->getName() : '',
                    $device->getIpAddress(),
                    $device->getName(),
                    $device->getNumber(),
                    $device->isEnabled() ? 'Sí' : 'No',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="devices.csv"');

        return $response;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInPlural("Equipos")
            ->setEntityLabelInSingular("Equipo");
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('location', 'Local'),
            TextField::new('ipAddress', 'Dirección IP'),
            TextField::new('name', 'Nombre POS'),
            TextField::new('number', 'Número POS'),
            BooleanField::new('enabled', 'Habilitado'),
        ];
    }
}
