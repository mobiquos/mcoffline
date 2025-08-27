<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Location;
use App\Entity\SyncEvent;
use App\Entity\SystemParameter;
use App\Entity\User;
use App\Form\ManualSyncForm;
use App\Message\SyncClients;
use App\Repository\ClientRepository;
use App\Security\CodeAuthenticatedUser;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\Messenger\MessageBusInterface;

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
            ->setDefaultSort(['id' => 'DESC'])
            ->setHelp(Crud::PAGE_INDEX, "Listado de sincronizaciones.");
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('location', 'Local'),
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
        ->add(Crud::PAGE_INDEX, Action::new("manualSync", "Subir archivo de clientes", 'sync')->linkToCrudAction('manualSync')->createAsGlobalAction())
        ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setLabel('Iniciar sincronización')->linkToCrudAction('startSync'))
        ;
    }

    public function startSync(AdminContext $context, EntityManagerInterface $em, MessageBusInterface $bus, HttpClientInterface $httpClient, UrlGeneratorInterface $urlGenerator): Response
    {
        $param = $em->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_SERVER_ADDRESS]);
        $locationCode = $em->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_LOCATION_CODE]);

        $response = $httpClient->request('GET', $param->getValue() . '/' .$urlGenerator->generate('app_sync_pull_start', ['locationCode' => $locationCode->getValue()], UrlGeneratorInterface::RELATIVE_PATH));
        $data = $response->toArray();
        if ($response->getStatusCode() != 200) {
            $aug = $this->container->get(AdminUrlGenerator::class);
            $this->addFlash('danger', 'Ocurrio un error al intentar iniciar la sincronización.');
            return $this->redirect($aug->setAction(Action::INDEX)->setController(static::class));
        }

        $syncEvent = new SyncEvent();

        if ($this->getUser() instanceof CodeAuthenticatedUser) {
            /** @var UserRepository */
            $userRepository = $em->getRepository(User::class);
            $user = $userRepository->find($this->getUser()->getOriginalUser()->getId());
            $syncEvent->setCreatedBy($user);
        } else {
            $syncEvent->setCreatedBy($this->getUser());
        }

        $syncEvent->setStatus(SyncEvent::STATUS_INPROGRESS);
        $em->persist($syncEvent);
        $em->flush();

        $bus->dispatch(new SyncClients($data['syncEventId']));

        $this->addFlash('success', 'La sincronización de clientes ha comenzado.');

        $aug = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($aug->setAction(Action::INDEX)->setController(static::class));
    }

    public function manualSync(AdminContext $context, EntityManagerInterface $em, AdminUrlGenerator $aug): Response
    {
        $form = $this->createForm(ManualSyncForm::class, []);
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $locationCode = $em->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_LOCATION_CODE]);
            $location = $em->getRepository(Location::class)->findByCode($locationCode->getValue());

            $entity = new SyncEvent();

            if ($this->getUser() instanceof CodeAuthenticatedUser) {
                /** @var UserRepository */
                $userRepository = $em->getRepository(User::class);
                $user = $userRepository->find($this->getUser()->getOriginalUser()->getId());
                $entity->setCreatedBy($user);
            } else {
                $entity->setCreatedBy($this->getUser());
            }

            $entity->setStatus(SyncEvent::STATUS_PENDING);
            $entity->setLocation($location);
            $em->persist($entity);
            $em->flush();

            // Save the uploaded file with the SyncEvent ID as filename
            /* @var UploadedFile */
            $uploadedFile = $form->getData()['uploadedFile'];
            $uploadsDirectory = $this->getParameter('kernel.project_dir') . '/var/uploads/sync';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadsDirectory)) {
                mkdir($uploadsDirectory, 0755, true);
            }
            
            // Move the file with the SyncEvent ID as name
            $filename = $entity->getId() . '.csv';
            $uploadedFile->move($uploadsDirectory, $filename);

            $this->addFlash('success', 'Archivo cargado exitosamente. La sincronización comenzará en breve.');

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
