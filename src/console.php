#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Command\Build;
use Symfony\Component\Console\Application;

require __DIR__ . '/../vendor/autoload.php';

$application = new Application();
$application->add(new Build());
$application->run();
