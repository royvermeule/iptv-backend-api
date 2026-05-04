<?php

declare(strict_types=1);

$binary = PHP_OS_FAMILY === 'Windows' ? '.\\rr.exe' : './rr';
passthru("$binary serve -c rr.yaml");
