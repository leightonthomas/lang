<?php

declare(strict_types=1);

namespace App\Model\TypeChecker;

use function ltrim;
use function sprintf;

final class Scope
{
    /** @var array<string, string> */
    private array $variables;

    public function __construct(
        private readonly string $name,
        public readonly ?Scope $parent = null,
    ) {
        $this->variables = [];
    }

    public function getScopedName(): string
    {
        // we may not have a parent, so trim the . from the start rather than deal with conditional logic because lazy
        return ltrim(sprintf("%s.%s", $this->parent?->getScopedName(), $this->name), '.');
    }

    public function makeChildScope(string $name): Scope
    {
        return new Scope($name, $this);
    }

    public function addUnscopedVariable(string $unscopedVariable): void
    {
        $this->variables[$unscopedVariable] = $this->asUnregisteredScopedVariable($unscopedVariable);
    }

    /**
     * @return string the unscoped variable if it were to be a part of this scope. it hasn't been added, though.
     */
    public function asUnregisteredScopedVariable(string $unscopedVariable): string
    {
        return sprintf("%s.%s", $this->getScopedName(), $unscopedVariable);
    }

    public function getScopedVariable(string $unscopedVariable): ?string
    {
        return $this->variables[$unscopedVariable] ?? $this->parent?->getScopedVariable($unscopedVariable) ?? null;
    }
}
