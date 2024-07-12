<?php

declare(strict_types=1);

namespace App\Command;

use App\Checking\FunctionCallArgumentCountChecker;
use App\Checking\InferenceChecker;
use App\Checking\ReturnTypeChecker;
use App\Compiler\CustomBytecode\ProgramCompiler;
use App\Compiler\Program;
use App\Inference\Instantiator;
use App\Inference\TypeInferer;
use App\Lexer\Lexer;
use App\Model\Compiler\CustomBytecode\Standard\Function\StandardFunction;
use App\Model\Exception\Parser\ParseFailure;
use App\Model\Keyword;
use App\Model\StandardType;
use App\Parser\Parser;
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
        $parser = new Parser($this->getReservedIdentifiers());
        $inferenceChecker = new InferenceChecker(
            new TypeInferer(new Instantiator()),
        );
        $returnTypeChecker = new ReturnTypeChecker();
        $functionCallChecker = new FunctionCallArgumentCountChecker();
        $compiler = new ProgramCompiler();

        $tokens = $lexer->lex(fopen($file, 'r'));

        try {
            $parseResult = $parser->parse($tokens);
        } catch (ParseFailure $e) {
            $style->error($e->getMessage());

            var_dump($e->lastToken);

            return Command::FAILURE;
        }

        $inferenceResult = $inferenceChecker->check($parseResult);

        $program = new Program($parseResult, $inferenceResult['context'], $inferenceResult['types']);

        $returnTypeChecker->check($program);
        $functionCallChecker->check($program);

        $compilerOutput = $compiler->compile($program);

        $filesystem = new Filesystem();
        $filesystem->dumpFile('./build/program', $compilerOutput);

        $style->success(sprintf("Compiled program to %s", realpath('./build/program')));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, bool>
     */
    private function getReservedIdentifiers(): array
    {
        /** @var array<string, bool> $reservedIdentifiers */
        $reservedIdentifiers = [];
        foreach (Keyword::cases() as $keyword) {
            $reservedIdentifiers[$keyword->value] = true;
        }

        /** @var class-string<StandardFunction> $standardFunction */
        foreach (StandardFunction::FUNCTIONS as $standardFunction) {
            $reservedIdentifiers[$standardFunction::getName()] = true;
        }

        /** @var class-string<StandardFunction> $standardFunction */
        foreach (StandardType::cases() as $standardType) {
            $reservedIdentifiers[$standardType->value] = true;
        }

        return $reservedIdentifiers;
    }
}
