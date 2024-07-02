<?php

declare(strict_types=1);

namespace App\Inference;

use App\Model\Inference\Type\Application;
use App\Model\Inference\Type\Monotype;
use App\Model\Inference\Type\Polytype;
use App\Model\Inference\Type\Quantifier;
use App\Model\Inference\Type\Variable;

use function array_map;
use function get_class;

/**
 * @psalm-type Mappings = array<string, Monotype>
 */
final class Instantiator
{
    private int $variableCounter = 0;

    public function __invoke(Polytype $input): Monotype
    {
        return $this->instantiate($input, []);
    }

    public function newVariable(): Variable
    {
        $name = "x_$this->variableCounter";
        $this->variableCounter++;

        return new Variable($name);
    }

    private function instantiate(Polytype $input, array $mappings): Monotype
    {
        return match (get_class($input)) {
            Variable::class => $mappings[$input->name] ?? $input,
            Application::class => new Application(
                $input->constructor,
                array_map(
                    fn (Monotype $t): Monotype => $this->instantiate($t, $mappings),
                    $input->arguments,
                ),
            ),
            // specialise by replacing the quantifier with a variable that can later be specified
            Quantifier::class => $this->instantiate(
                $input->body,
                array_merge($mappings, [$input->quantified => $this->newVariable()]),
            ),
        };
    }
}


