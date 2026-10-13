<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

use function count;
use function dirname;
use function is_array;
use function is_string;

/**
 * Every locale must define exactly the keys of the reference locale (en), and every literal
 * NowoPerformanceBundle key used in the bundle templates must exist in it.
 */
final class TranslationKeyParityTest extends TestCase
{
    private const DOMAIN_DIR = '/src/Resources/translations';

    public function testEveryLocaleHasTheSameKeysAsEnglish(): void
    {
        $root      = dirname(__DIR__, 3);
        $reference = $this->keys($root . self::DOMAIN_DIR . '/NowoPerformanceBundle.en.yaml');
        $files     = glob($root . self::DOMAIN_DIR . '/NowoPerformanceBundle.*.yaml') ?: [];

        $this->assertGreaterThan(1, count($files));

        foreach ($files as $file) {
            $keys = $this->keys($file);
            $this->assertSame([], array_values(array_diff($reference, $keys)), basename($file) . ' misses keys');
            $this->assertSame([], array_values(array_diff($keys, $reference)), basename($file) . ' has extra keys');
        }
    }

    public function testEveryTemplateKeyExistsInEnglish(): void
    {
        $root      = dirname(__DIR__, 3);
        $reference = array_flip($this->keys($root . self::DOMAIN_DIR . '/NowoPerformanceBundle.en.yaml'));
        $missing   = [];
        $iterator  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src/Resources/views'));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            preg_match_all(
                "/'([A-Za-z0-9_.]+)'\\s*\\|\\s*trans\\s*\\(\\s*(?:\\{[^}]*\\}|[a-z_]+)?\\s*,\\s*'NowoPerformanceBundle'/",
                (string) file_get_contents($file->getPathname()),
                $matches,
            );
            foreach ($matches[1] as $key) {
                if (!isset($reference[$key])) {
                    $missing[] = $file->getFilename() . ': ' . $key;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    /**
     * Translation keys passed as literals by form types and controllers (labels, placeholders,
     * "all ..." entries) must exist in every locale with a real value, never the key itself.
     */
    public function testEveryFormAndControllerKeyIsTranslatedInEveryLocale(): void
    {
        $root     = dirname(__DIR__, 3);
        $sections = array_keys((array) Yaml::parseFile($root . self::DOMAIN_DIR . '/NowoPerformanceBundle.en.yaml'));
        $pattern  = "/'((?:" . implode('|', array_map(static fn (int|string $s): string => preg_quote((string) $s, '/'), $sections)) . ")\\.[a-z0-9_.]+)'/";
        $used     = [];
        foreach (array_merge(glob($root . '/src/Form/*.php') ?: [], glob($root . '/src/Controller/*.php') ?: []) as $file) {
            preg_match_all($pattern, (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $key) {
                $used[$key] = true;
            }
        }

        $this->assertArrayHasKey('access_statistics.all_routes', $used);
        $this->assertArrayHasKey('access_statistics.all_status_codes', $used);

        $missing = [];
        foreach (glob($root . self::DOMAIN_DIR . '/NowoPerformanceBundle.*.yaml') ?: [] as $file) {
            $values = $this->values($file);
            foreach (array_keys($used) as $key) {
                $value = $values[$key] ?? null;
                if (!is_string($value) || trim($value) === '' || $value === $key) {
                    $missing[] = basename($file) . ': ' . $key;
                }
            }
        }

        $this->assertSame([], $missing);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(string $file): array
    {
        $data = Yaml::parseFile($file);
        $this->assertIsArray($data, $file);
        $values = [];
        $walk   = static function (array $node, string $prefix) use (&$walk, &$values): void {
            foreach ($node as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
                if (is_array($value)) {
                    $walk($value, $path);
                } else {
                    $values[$path] = $value;
                }
            }
        };
        $walk($data, '');

        return $values;
    }

    /**
     * @return list<string>
     */
    private function keys(string $file): array
    {
        $data = Yaml::parseFile($file);
        $this->assertIsArray($data, $file);

        return $this->flatten($data);
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                array_push($keys, ...$this->flatten($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    }
}
