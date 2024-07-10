<?php

declare(strict_types=1);

namespace App\Compiler\CustomBytecode;

use RuntimeException;

use function array_key_last;
use function array_pop;
use function join;

/**
 * Writes instructions & handles "grouping" to keep compilation process simpler
 */
final class InstructionWriter
{
    /** @var list<string> the output list of packed binary strings */
    private array $instructions;

    /** @var array<int, list<string>> instruction groupings */
    private array $groups;

    public function __construct()
    {
        $this->instructions = [];
        $this->groups = [];
    }

    public function write(string $binaryString): void
    {
        if (! $this->hasGroup()) {
            $this->instructions[] = $binaryString;

            return;
        }

        $this->groups[array_key_last($this->groups)][] = $binaryString;
    }

    public function append(array $binaryStrings): void
    {
        foreach ($binaryStrings as $binaryString) {
            $this->write($binaryString);
        }
    }

    public function startGroup(): void
    {
        $this->groups[] = [];
    }

    /**
     * @return list<string> the instructions that were in the group
     */
    public function endGroup(): array
    {
        if (! $this->hasGroup()) {
            throw new RuntimeException("No active group");
        }

        return array_pop($this->groups);
    }

    public function finish(): string
    {
        if ($this->hasGroup()) {
            throw new RuntimeException("Cannot finish writing, open group");
        }

        return join('', $this->instructions);
    }

    private function hasGroup(): bool
    {
        $group = $this->groups[array_key_last($this->groups)] ?? null;

        return $group !== null;
    }
}
