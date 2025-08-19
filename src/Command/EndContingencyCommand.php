<?php

namespace App\Command;

use App\Entity\Contingency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:end-contingency',
    description: 'Ends the current active contingency, if any.',
)]
class EndContingencyCommand extends Command
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to end an active contingency by setting its end date.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $activeContingency = $this->entityManager->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);

        if ($activeContingency) {
            $activeContingency->setEndedAt(new \DateTime());
            $this->entityManager->flush();

            $io->success('The active contingency has been successfully ended.');
        } else {
            $io->info('No active contingency found.');
        }

        return Command::SUCCESS;
    }
}
