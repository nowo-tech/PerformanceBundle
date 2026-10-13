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
