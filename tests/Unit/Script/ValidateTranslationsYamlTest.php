<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Script;

use PHPUnit\Framework\TestCase;

/**
 * Tests for scripts/validate-translations-yaml.php (exit codes, duplicate detection, valid YAML).
 */
final class ValidateTranslationsYamlTest extends TestCase
{
    private static string $scriptPath;
    private static string $projectRoot;

    public static function setUpBeforeClass(): void
    {
        // tests/Unit/Script -> go up 3 levels to project root
        self::$projectRoot = dirname(__DIR__, 3);
        self::$scriptPath = self::$projectRoot.'/scripts/validate-translations-yaml.php';
    }

    public function testScriptExists(): void
    {
        $this->assertFileExists(self::$scriptPath);
    }

    public function testExitCodeZeroWhenValidYamlNoDuplicates(): void
    {
        $dir = $this->createTempDir();
        $this->addFile($dir.'/valid.yaml', "a: 1\nb: 2\n");

        [$code, $stdout, $stderr] = $this->runScript($dir);

        $this->assertSame(0, $code, 'Expected exit 0. stderr: '.$stderr);
        $this->assertStringContainsString('valid', $stdout);
        $this->assertSame('', trim($stderr));
    }

    public function testExitCodeOneWhenDuplicateKeySameBlock(): void
    {
        $dir = $this->createTempDir();
        $this->addFile($dir.'/dup.yaml', "a: 1\nb: 2\na: 3\n");

        [$code, $stdout, $stderr] = $this->runScript($dir);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Duplicate key', $stderr);
        $this->assertStringContainsString('"a"', $stderr);
        $this->assertStringContainsString('dup.yaml', $stderr);
    }

    public function testSameKeyDifferentBlocksNotReportedAsDuplicate(): void
    {
        $dir = $this->createTempDir();
        $yaml = "foo:\n  x: 1\nbar:\n  x: 2\n";
        $this->addFile($dir.'/same_key.yaml', $yaml);

        [$code, $stdout, $stderr] = $this->runScript($dir);

        $this->assertSame(0, $code, 'Same key in different blocks should be valid. stderr: '.$stderr);
        $this->assertSame('', $stderr);
    }

    public function testExitCodeOneWhenDirectoryNotFound(): void
    {
        $bad = self::$projectRoot.'/nonexistent_translations_dir_'.uniqid('', true);

        [$code, , $stderr] = $this->runScript($bad);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Directory not found', $stderr);
    }

    public function testExitCodeOneWhenNoYamlFiles(): void
    {
        $dir = $this->createTempDir();
        $this->addFile($dir.'/readme.txt', 'not yaml');

        [$code, , $stderr] = $this->runScript($dir);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('No YAML files', $stderr);
    }

    public function testRealTranslationsDirPasses(): void
    {
        $dir = self::$projectRoot.'/src/Resources/translations';
        if (!is_dir($dir)) {
            $this->markTestSkipped('Translations dir not found.');
        }

        [$code, $stdout, $stderr] = $this->runScript($dir);

        $this->assertSame(0, $code, 'Real translations should pass. stderr: '.$stderr);
        $this->assertStringContainsString('valid', $stdout);
    }

    public function testExitCodeOneWhenInvalidYamlSyntax(): void
    {
        if (!\function_exists('yaml_parse')) {
            $this->markTestSkipped('ext-yaml required to detect invalid YAML.');
        }

        $dir = $this->createTempDir();
        $this->addFile($dir.'/bad.yaml', "a: 1\nb: [unclosed\n");

        [$code, $stdout, $stderr] = $this->runScript($dir);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Invalid YAML', $stderr);
        $this->assertStringContainsString('bad.yaml', $stderr);
    }

    public function testDuplicateInNestedBlockSameParentReported(): void
    {
        $dir = $this->createTempDir();
        $yaml = "parent:\n  level2:\n    key: 1\n    key: 2\n";
        $this->addFile($dir.'/nested.yaml', $yaml);

        [$code, , $stderr] = $this->runScript($dir);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Duplicate key', $stderr);
        $this->assertStringContainsString('"key"', $stderr);
        $this->assertStringContainsString('nested.yaml', $stderr);
    }

    public function testThreeLevelNestingSameKeyDifferentBlocksValid(): void
    {
        $dir = $this->createTempDir();
        $yaml = "a:\n  x:\n    id: 1\nb:\n  x:\n    id: 2\n";
        $this->addFile($dir.'/three.yaml', $yaml);

        [$code, $stdout, $stderr] = $this->runScript($dir);

        $this->assertSame(0, $code, 'Same key at different nesting under different parents should be valid. stderr: '.$stderr);
        $this->assertSame('', $stderr);
    }

    public function testCommentsAndBlankLinesDoNotBreakDuplicateDetection(): void
    {
        $dir = $this->createTempDir();
        $yaml = "section:\n  # comment\n  first: 1\n\n  second: 2\n  first: 3\n";
        $this->addFile($dir.'/with_comments.yaml', $yaml);

        [$code, , $stderr] = $this->runScript($dir);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Duplicate key', $stderr);
        $this->assertStringContainsString('"first"', $stderr);
    }

    public function testDuplicateReportedLineNumberIsSecondOccurrence(): void
    {
        $dir = $this->createTempDir();
        $yaml = "root:\n  alpha: 1\n  beta: 2\n  alpha: 3\n";
        $this->addFile($dir.'/lines.yaml', $yaml);

        [$code, , $stderr] = $this->runScript($dir);

        $this->assertSame(1, $code);
        $this->assertMatchesRegularExpression('/lines\.yaml:\d+:\s*Duplicate key/', $stderr);
        $this->assertStringContainsString('"alpha"', $stderr);
    }

    public function testUsesDefaultTranslationsDirWhenNoArgument(): void
    {
        $defaultDir = self::$projectRoot.'/src/Resources/translations';
        if (!is_dir($defaultDir)) {
            $this->markTestSkipped('Default translations dir not found.');
        }

        [$code, $stdout, $stderr] = $this->runScriptWithNoArg();

        $this->assertSame(0, $code, 'Script with no arg should use default dir and pass. stderr: '.$stderr);
        $this->assertStringContainsString('valid', $stdout);
    }

    public function testDuplicateAtRootLevelReported(): void
    {
        $dir = $this->createTempDir();
        $yaml = "foo: 1\nbar: 2\nfoo: 3\n";
        $this->addFile($dir.'/root_dup.yaml', $yaml);

        [$code, , $stderr] = $this->runScript($dir);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Duplicate key', $stderr);
        $this->assertStringContainsString('"foo"', $stderr);
        $this->assertStringContainsString('root_dup.yaml', $stderr);
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir().'/nowo_perf_yaml_'.uniqid('', true);
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function addFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }

    /**
     * @return array{0: int, 1: string, 2: string} [exitCode, stdout, stderr]
     */
    private function runScript(string $translationsDir): array
    {
        $php = \defined('PHP_BINARY') && \PHP_BINARY !== '' ? \PHP_BINARY : 'php';
        $cmd = [$php, self::$scriptPath, $translationsDir];

        $proc = proc_open(
            $cmd,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            self::$projectRoot,
            null
        );

        $this->assertNotFalse($proc);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code < 0) {
            $code = 1;
        }

        return [$code, $stdout !== false ? $stdout : '', $stderr !== false ? $stderr : ''];
    }

    /**
     * Run script with no directory argument (uses script default).
     *
     * @return array{0: int, 1: string, 2: string} [exitCode, stdout, stderr]
     */
    private function runScriptWithNoArg(): array
    {
        $php = \defined('PHP_BINARY') && \PHP_BINARY !== '' ? \PHP_BINARY : 'php';
        $cmd = [$php, self::$scriptPath];

        $proc = proc_open(
            $cmd,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            self::$projectRoot,
            null
        );

        $this->assertNotFalse($proc);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code < 0) {
            $code = 1;
        }

        return [$code, $stdout !== false ? $stdout : '', $stderr !== false ? $stderr : ''];
    }
}
