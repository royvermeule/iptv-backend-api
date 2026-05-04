<?php

declare(strict_types=1);

$consolePath = __DIR__ . '/console.php';
$pipes       = [];

$jobsProc = proc_open(
    [PHP_BINARY, $consolePath, 'jobs:run-all'],
    [
        0 => ['pipe', 'r'],
        1 => STDOUT,
        2 => STDERR,
    ],
    $pipes,
);

$binary = PHP_OS_FAMILY === 'Windows' ? '.\\rr.exe' : './rr';
passthru("$binary serve -c rr.yaml");

if (is_resource($jobsProc)) {
    proc_terminate($jobsProc);
    proc_close($jobsProc);
}
