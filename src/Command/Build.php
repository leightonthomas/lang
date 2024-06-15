<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function var_dump;

#[AsCommand('build', 'Build from source files')]
class Build extends Command
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        var_dump("hi");
        return 0;
    }
}
