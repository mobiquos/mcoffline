<?php

namespace App\Controller\Admin;

use App\Entity\Contingency;
use App\Entity\Location;
use App\Entity\SystemParameter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use App\Entity\SyncEvent;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class ContingencyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Contingency::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural("Contingencias")
            ->setEntityLabelInSingular("Contingencia")
            ->setPageTitle(Crud::PAGE_NEW, "Iniciar contingencia")
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
                $url = $this->container->get(AdminUrlGenerator::class)->setController(DashboardController::class)->setAction('index')->generateUrl();
                return $this->redirect($url);
            }
        }

        return parent::new($context);
    }

    public function createEntity(string $entityFqcn)
    {
        $contingency = new Contingency();
        $contingency->setStartedAt(new \DateTime());
        $contingency->setStartedBy($this->getUser());

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
            AssociationField::new('location', "Locación")->setDisabled()->onlyOnForms(),
            TextField::new('locationCode', "Código Locación")->setDisabled()->hideOnForm(),
            DateTimeField::new('startedAt', "Inicio")->hideWhenUpdating(),
            DateTimeField::new('endedAt', "Termino")->hideWhenCreating(),
            AssociationField::new('startedBy', "Iniciada por")->hideOnForm()->setDisabled(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE);
    }
}
