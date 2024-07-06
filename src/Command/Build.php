<?php

declare(strict_types=1);

namespace App\Command;

use App\Compiler\CustomBytecode\ProgramCompiler;
use App\Inference\Instantiator;
use App\Inference\TypeInferer;
use App\Lexer\Lexer;
use App\Model\Exception\Parser\ParseFailure;
use App\Parser\Parser;
use App\TypeChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

use function file_exists;
use function fopen;
use function realpath;
use function sprintf;
use function var_dump;

#[AsCommand('build', 'Build from source files.')]
class Build extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'The path to the source file to build.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $file = $input->getArgument('file');
        if (! file_exists($file)) {
            $style->error("Failed to find file '$file'");

            return Command::FAILURE;
        }

        $lexer = new Lexer();
        $parser = new Parser();
        $typeChecker = new TypeChecker(
            new TypeInferer(new Instantiator()),
        );
        $compiler = new ProgramCompiler();

        $tokens = $lexer->lex(fopen($file, 'r'));

        try {
            $parseResult = $parser->parse($tokens);
        } catch (ParseFailure $e) {
            $style->error($e->getMessage());

            var_dump($e->lastToken);

            return Command::FAILURE;
        }

        $typeChecker->checkTypes($parseResult);

        $compilerOutput = $compiler->compile($parseResult);

        $filesystem = new Filesystem();
        $filesystem->dumpFile('./build/program', $compilerOutput);

        $style->success(sprintf("Compiled program to %s", realpath('./build/program')));

        return Command::SUCCESS;
    }
}
