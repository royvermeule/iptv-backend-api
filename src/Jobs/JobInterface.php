<?php

declare(strict_types=1);

namespace App\Jobs;

interface JobInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function run(): void;
}
