<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\SyncEvent;
use App\Form\ManualSyncForm;
use App\Repository\ClientRepository;
use Doctrine\DBAL\Types\TextType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

class SyncEventCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SyncEvent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural("Sincronizaciones")
            ->setEntityLabelInSingular("Sincronización")
            ->setPageTitle(Crud::PAGE_NEW, "Iniciar sincronización")
            ->setHelp(Crud::PAGE_INDEX, "Listado de sincronizaciones.");
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            DateTimeField::new('createdAt', 'Fecha'),
            AssociationField::new('createdBy', 'Iniciada por'),
            TextField::new('status', 'Estado')
                ->setTemplatePath('admin/field/sync_status.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return
        $actions->remove(Crud::PAGE_INDEX, Action::DELETE)
        ->remove(Crud::PAGE_INDEX, Action::EDIT)
        ->add(Crud::PAGE_INDEX, Action::new("manualSync", "Sincronización manual", 'sync')->linkToCrudAction('manualSync')->createAsGlobalAction())
        ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setLabel('Iniciar sincronización'))
        ;
    }

    public function manualSync(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $aug): Response
    {
        $form = $this->createForm(ManualSyncForm::class, []);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $this->processUploadedFiles($form);

            /* @var ClientRepository */
            $clientRepository = $em->getRepository(Client::class);
            $clientRepository->removeAll();


            $entity = new SyncEvent();
            $entity->setCreatedBy($this->getUser());
            $entity->setStatus(SyncEvent::STATUS_INPROGRESS);
            $em->persist($entity);
            $em->flush();

            $em->beginTransaction();
            try {
                /* @var UploadedFile */
                $uploadedFile = $form->getData()['uploadedFile'];

                ini_set('memory_limit', '-1');
                ini_set('max_execution_time', '120');
                $filefile = file($uploadedFile->getRealPath());
                foreach ($filefile as $line) {
                    $client = $this->parseClient($line);
                    $em->persist($client);
                }
                $em->commit();
                $entity->setStatus(SyncEvent::STATUS_SUCCESS);
                $em->flush();
            } catch (Exception $e) {
                $em->rollback();
                $entity->setStatus(SyncEvent::STATUS_FAILED);
                $em->flush();
                $this->addFlash('danger', sprintf("Ocurrio un problema: %s", $e->getMessage()));
                return $this->redirect($aug->setAction(Action::INDEX)->setController(SyncEventCrudController::class));
            }
            return $this->redirect($aug->setAction(Action::INDEX)->setController(SyncEventCrudController::class));
        }

        return $this->render('admin/sync_event/new_manual.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function parseClient(string $string): Client
    {
        $data = explode(";", preg_replace('/[[:^print:]]/', '', $string));

        $client = new Client();
        $client->setRut(str_replace(["-", "."], "", trim($data[0], "0")));
        $client->setFirstLastName($data[1]);
        $client->setSecondLastName($data[2]);
        $client->setName($data[3]);
        $client->setCreditLimit($data[4]);
        $client->setCreditAvailable($data[5]);
        $client->setBlockComment($data[6]);
        $client->setOverdue($data[7]);
        $client->setNextBillingAt(\DateTime::createFromFormat("d/m/Y", $data[8]));
        $client->setLastUpdatedAt(new \DateTime());

        return $client;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
