<?php

namespace App\Command;

use App\Entity\SystemParameter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;

class SystemParamsInitCommand extends Command
{
    private $em;

    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:params:init')
          ->setDescription('Genera o actualiza los parámetros básicos del sistema.')
          ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
// $this->em->getRepository(SystemParameter::class)->createQueryBuilder('e')->delete();
//         $this->em->flush();
        foreach (SystemParameter::PARAMS as $key => $value) {
            $result = $this->em->getRepository(SystemParameter::class)->findBy(["code" => $key]);
            if (current($result) != false) {
                continue;
            }

            $entity = new SystemParameter();
            $entity->setCode($key);
            $entity->setValue($value["defaultValue"]);

            $this->em->persist($entity);
        }
        $this->em->flush();

        return Command::SUCCESS;
    }
}
