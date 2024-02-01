<?php
// src/Command/DeleteAllTestingEntitiesCommand.php

namespace App\Command;

use App\Entity\Testing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteAllTestingEntitiesCommand extends Command
{
    protected static $defaultName = 'app:delete-all-testing';

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setDescription('Deletes all Testing entities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->entityManager->getRepository(Testing::class);
        $allTestingEntities = $repository->findAll();

        foreach ($allTestingEntities as $entity) {
            $this->entityManager->remove($entity);
        }

        $this->entityManager->flush();

        $output->writeln('All Testing entities deleted successfully.');

        return Command::SUCCESS;
    }
}
