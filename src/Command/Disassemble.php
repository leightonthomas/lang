<?php

declare(strict_types=1);

namespace App\Command;

use App\Compiler\CustomBytecode\Disassembler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function file_exists;
use function fopen;

#[AsCommand('disassemble', 'Disassemble a compiled program.')]
class Disassemble extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'The path to the compiled program.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');
        if (! file_exists($file)) {
            $style->error("Failed to find file '$file'");

            return Command::FAILURE;
        }

        $disassembler = new Disassembler();

        $handle = fopen($file, 'r');

        try {
            $output = $disassembler->disassemble($handle);
        } finally {
            fclose($handle);
        }

        $style->success("Successfully disassembled");
        $style->writeln($output);

        return Command::SUCCESS;
    }
}
