<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Jobs\JobRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JobsRunCommand extends Command
{
    public function __construct(private readonly JobRegistry $registry)
    {
        parent::__construct('jobs:run');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run a job by name')
            ->addArgument('name', InputArgument::REQUIRED, 'The job name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        try {
            $job = $this->registry->get($name);
        } catch (\InvalidArgumentException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Starting job: {$name}</info>");
        $job->run();

        return Command::SUCCESS;
    }
}
