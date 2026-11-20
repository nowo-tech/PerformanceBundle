<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use Nowo\PerformanceBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\XmlReferenceDumper;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

use function array_chunk;
use function array_filter;
use function array_merge;
use function dirname;
use function explode;
use function fclose;
use function implode;
use function is_resource;
use function json_decode;
use function proc_close;
use function proc_open;
use function sort;
use function str_starts_with;
use function stream_get_contents;
use function stream_set_timeout;
use function var_export;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

/**
 * PHP 8.3+ stops the compiler at zend.max_allowed_stack_size ("Maximum call stack size ... reached during
 * compilation"). A FrankenPHP thread on Alpine (musl) has a 512 KiB stack, so a file whose compilation needs more
 * cannot be loaded there. One long fluent chain in a configuration tree needs about 1 KiB of stack per call, so the
 * trees are built by one method per section.
 *
 * The CLI main thread of the test run has 8 MiB, so these tests run PHP in a child process with the stack capped at
 * the FrankenPHP thread size.
 */
#[CoversClass(Configuration::class)]
#[RequiresPhp('>= 8.3.0')]
final class ConfigurationThreadStackTest extends TestCase
{
    /** The stack of a FrankenPHP thread on Alpine (dunglas/frankenphp:*-alpine). */
    private const THREAD_STACK = '512K';

    private const ROOT = __DIR__ . '/../../..';

    /**
     * @return array<string, array{class-string<ConfigurationInterface>}>
     */
    public static function configurations(): array
    {
        return [
            'Configuration' => [Configuration::class],
        ];
    }

    /**
     * @param class-string<ConfigurationInterface> $class
     */
    #[DataProvider('configurations')]
    public function testTheConfigurationLoadsBuildsAndProcessesOnAFrankenPhpThreadStack(string $class): void
    {
        [$code, $stdout, $stderr] = $this->php([dirname(__DIR__, 2) . '/Fixtures/configuration-thread-stack.php', $class]);

        self::assertSame(0, $code, $stdout . $stderr);
        /** @var array{defaults: string, yaml: string, xml: string} $child */
        $child         = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        $configuration = new $class();

        try {
            $defaults = var_export((new Processor())->processConfiguration($configuration, [[]]), true);
        } catch (InvalidConfigurationException $e) {
            $defaults = $e->getMessage();
        }
        self::assertSame($defaults, $child['defaults']);
        self::assertSame((new YamlReferenceDumper())->dump($configuration), $child['yaml']);
        self::assertSame((new XmlReferenceDumper())->dump($configuration), $child['xml']);
    }

    /**
     * Every class of the bundle may be loaded inside a request, so every source file must compile on that stack.
     */
    public function testEverySourceFileCompilesOnAFrankenPhpThreadStack(): void
    {
        $files = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/src', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        self::assertNotEmpty($files);

        foreach (array_chunk($files, 50) as $chunk) {
            // php -l takes several files since PHP 8.3 and compiles each one.
            [$code, $stdout, $stderr] = $this->php(array_merge(['-l'], $chunk));

            self::assertSame(0, $code, $this->withoutSuccesses($stdout) . $stderr);
        }
    }

    /**
     * `php -l` prints one line per compiled file; only the failures are of interest.
     */
    private function withoutSuccesses(string $output): string
    {
        return implode("\n", array_filter(explode("\n", $output), static fn (string $line): bool => !str_starts_with($line, 'No syntax errors')));
    }

    /**
     * @param list<string> $args
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function php(array $args): array
    {
        // A test helper, not worker code: the pipes get a read timeout below.
        $process = proc_open( // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout
            array_merge([PHP_BINARY, '-d', 'zend.max_allowed_stack_size=' . self::THREAD_STACK, '-d', 'xdebug.mode=off'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            self::ROOT,
        );
        self::assertTrue(is_resource($process));
        stream_set_timeout($pipes[1], 60);
        stream_set_timeout($pipes[2], 60);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
