<?php

namespace Tests\Feature;

use Modules\Chascarrillo\Service\ThemeAssetPublisher;
use PHPUnit\Framework\TestCase;

class ThemeAssetPublisherTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/chascarrillo-assets-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0755, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        parent::tearDown();
    }

    public function testPublishingIsOrderedSelectiveIdempotentAndPreservesUnknownFiles(): void
    {
        $public = $this->root . '/public_html';
        $this->writeFile(
            $this->root . '/vendor/alxarafe/alxarafe/templates/themes/default/css/default.css',
            'framework'
        );
        $this->writeFile($this->root . '/templates/themes/default/css/default.css', 'application override');
        $this->writeFile($this->root . '/templates/themes/default/css/not-an-asset.blade.php', '@extends("layout")');
        $this->writeFile($this->root . '/templates/themes/cyberpunk/js/theme.js', 'console.log("ok");');

        $stale = $public . '/themes/default/css/obsolete.css';
        $modified = $public . '/themes/default/css/customized-old.css';
        $unknown = $public . '/themes/my-theme/css/custom.css';
        $this->writeFile($stale, 'old managed');
        $this->writeFile($modified, 'user changed this');
        $this->writeFile($unknown, 'unknown user file');
        $this->writeFile(
            $public . '/themes/' . ThemeAssetPublisher::MANIFEST,
            json_encode([
                'format' => 2,
                'files' => [
                    [
                        'path' => 'default/css/obsolete.css',
                        'sha256' => hash('sha256', 'old managed'),
                    ],
                    [
                        'path' => 'default/css/customized-old.css',
                        'sha256' => hash('sha256', 'original managed'),
                    ],
                ],
            ], JSON_PRETTY_PRINT) ?: '{}'
        );

        $publisher = new ThemeAssetPublisher();
        $first = $publisher->publish($this->root, $public);

        self::assertSame('application override', file_get_contents($public . '/themes/default/css/default.css'));
        self::assertSame('console.log("ok");', file_get_contents($public . '/themes/cyberpunk/js/theme.js'));
        self::assertFileDoesNotExist($stale);
        self::assertFileExists($modified);
        self::assertFileExists($unknown);
        self::assertFileDoesNotExist($public . '/themes/default/css/not-an-asset.blade.php');
        self::assertSame(2, $first['copied']);
        self::assertSame(1, $first['removed']);
        self::assertSame(1, $first['preserved']);

        $snapshot = $this->snapshot($public . '/themes');
        $second = $publisher->publish($this->root, $public);
        self::assertSame($snapshot, $this->snapshot($public . '/themes'));
        self::assertSame(0, $second['copied']);
        self::assertSame(0, $second['removed']);
    }

    /** @return array<string,string> */
    private function snapshot(string $root): array
    {
        $result = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $hash = hash_file('sha256', $file->getPathname());
                self::assertNotFalse($hash);
                $result[substr($file->getPathname(), strlen($root) + 1)] = $hash;
            }
        }
        ksort($result);
        return $result;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            self::assertTrue(mkdir(dirname($path), 0755, true));
        }
        self::assertNotFalse(file_put_contents($path, $contents));
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $child = $path . '/' . $entry;
            is_dir($child) && !is_link($child) ? $this->removeTree($child) : unlink($child);
        }
        rmdir($path);
    }
}
