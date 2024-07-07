<?php

declare(strict_types=1);

namespace App\Command;

use App\Interpreter\CustomBytecodeInterpreter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

use function file_exists;
use function fopen;
use function memory_get_peak_usage;
use function sprintf;

#[AsCommand('run', 'Run a program.')]
class Run extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'The path to the compiled program to run.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $stopwatch = new Stopwatch(morePrecision: true);

        $file = $input->getArgument('file');
        if (! file_exists($file)) {
            $style->error("Failed to find file '$file'");

            return Command::FAILURE;
        }

        $handle = fopen($file, 'r');

        $interpreter = new CustomBytecodeInterpreter();

        try {
            $stopwatch->start('execution');
            $result = $interpreter->interpret($handle);
            $stopwatch->stop('execution');
        } finally {
            fclose($handle);
        }

        $executionMs = $stopwatch->getEvent('execution')->getDuration();

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $style->newLine(2);
            $style->writeln(sprintf("Return code: %d", $result));

            $style->newLine();
            $style->section('Stats');
            $style->table(
                ['Stat', 'Value'],
                [
                    ['Execution Time (ms)', $executionMs],
                    ['Memory Usage (MB)', memory_get_peak_usage() / 1_000_000],
                ],
            );
        }

        return $result;
    }
}
