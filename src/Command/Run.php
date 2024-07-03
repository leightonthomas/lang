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

use function file_exists;
use function file_get_contents;

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

        $file = $input->getArgument('file');
        if (! file_exists($file)) {
            $style->error("Failed to find file '$file'");

            return Command::FAILURE;
        }

        $interpreter = new CustomBytecodeInterpreter();

        return $interpreter->interpret(file_get_contents($file));
    }
}
