<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Jobs\JobRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JobsRunAllCommand extends Command
{
    public function __construct(private readonly JobRegistry $registry)
    {
        parent::__construct('jobs:run-all');
    }

    protected function configure(): void
    {
        $this->setDescription('Spawn all jobs as background processes and monitor them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobs = $this->registry->all();

        if (empty($jobs)) {
            $output->writeln('<comment>No jobs registered.</comment>');
            return Command::SUCCESS;
        }

        $consolePath = dirname(__DIR__, 3) . '/bin/console.php';
        $processes   = [];

        foreach ($jobs as $job) {
            $output->writeln("<info>Starting job: {$job->getName()}</info>");
            $processes[$job->getName()] = $this->spawn($consolePath, $job->getName());
        }

        $output->writeln('<info>All jobs running. Press Ctrl+C to stop.</info>');

        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGINT,  fn () => $this->shutdown($processes));
            pcntl_signal(SIGTERM, fn () => $this->shutdown($processes));
        }

        while (true) {
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            foreach ($processes as $name => $data) {
                $out = stream_get_contents($data['pipes'][1]);
                $err = stream_get_contents($data['pipes'][2]);

                if ($out !== false && $out !== '') {
                    echo $out;
                }
                if ($err !== false && $err !== '') {
                    fwrite(STDERR, $err);
                }

                $status = proc_get_status($data['proc']);
                if (!$status['running']) {
                    $output->writeln("<comment>Job '{$name}' exited (code {$status['exitcode']}), restarting...</comment>");
                    proc_close($data['proc']);
                    $processes[$name] = $this->spawn($consolePath, $name);
                }
            }

            usleep(500_000);
        }
    }

    private function spawn(string $consolePath, string $jobName): array
    {
        $pipes = [];
        $proc  = proc_open(
            [PHP_BINARY, $consolePath, 'jobs:run', $jobName],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return ['proc' => $proc, 'pipes' => $pipes];
    }

    private function shutdown(array $processes): never
    {
        foreach ($processes as $data) {
            proc_terminate($data['proc']);
            proc_close($data['proc']);
        }
        exit(0);
    }
}
