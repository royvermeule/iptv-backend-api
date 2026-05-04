<?php

declare(strict_types=1);

namespace App\Jobs;

class JobRegistry
{
    /** @var array<string, JobInterface> */
    private array $jobs = [];

    public function register(JobInterface $job): void
    {
        $this->jobs[$job->getName()] = $job;
    }

    public function get(string $name): JobInterface
    {
        return $this->jobs[$name]
            ?? throw new \InvalidArgumentException("Job '{$name}' not found.");
    }

    /** @return array<string, JobInterface> */
    public function all(): array
    {
        return $this->jobs;
    }
}
