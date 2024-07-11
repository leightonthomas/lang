<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use App\Command\Build;
use App\Command\Disassemble;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;

class DisassembleTest extends TestCase
{
    use MatchesSnapshots;

    private Build $build;
    private Disassemble $disassemble;

    public function setUp(): void
    {
        $this->build = new Build();
        $this->disassemble = new Disassemble();
    }

    #[Test]
    #[DataProvider('runProvider')]
    public function itWillBuildAndRunAProgramCorrectly(
        string $codeFixture,
    ): void {
        $build = new CommandTester($this->build);
        $buildResult = $build->execute(['file' => $codeFixture]);

        self::assertSame(Command::SUCCESS, $buildResult, $build->getDisplay());

        $disassemble = new CommandTester($this->disassemble);
        $disassemble->execute(['file' => __DIR__ . '/../../build/program']);

        $this->assertMatchesTextSnapshot($disassemble->getDisplay());
    }

    public static function runProvider(): Generator
    {
        $finder = new Finder();
        $files = $finder->files()->in(__DIR__ . '/../Fixtures/EndToEnd/Run')->name('*.txt');

        foreach ($files as $file) {
            yield $file->getFilenameWithoutExtension() => [$file->getRealPath()];
        }
    }

    protected function getSnapshotDirectory(): string
    {
        return __DIR__ . '/../Fixtures/EndToEnd/Disassemble';
    }

    protected function getSnapshotId(): string
    {
        return $this->dataName();
    }
}
