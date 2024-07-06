#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Command\Build;
use App\Command\Disassemble;
use App\Command\Run;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new Build());
$application->add(new Run());
$application->add(new Disassemble());
$application->run();
