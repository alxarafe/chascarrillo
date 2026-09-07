<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\Chascarrillo\Service\ManagedFileManifest;
use Modules\Chascarrillo\Service\RelativePath;
use Modules\Chascarrillo\Service\ReleaseArchiveValidator;
use Modules\Chascarrillo\Service\ReleaseInstaller;
use Modules\Chascarrillo\Service\ThemeAssetPublisher;
use Modules\Chascarrillo\Service\ReleaseValidator;
use Modules\Chascarrillo\Service\UpdateService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class ReleaseArtifactSecurityTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir() . '/chascarrillo-artifact-test-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workspace, 0755, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->workspace);
        parent::tearDown();
    }

    /** @return iterable<string,array{string}> */
    public static function invalidRelativePaths(): iterable
    {
        yield 'empty' => [''];
        yield 'dot segment' => ['a/./b'];
        yield 'duplicate separator' => ['a//b'];
        yield 'parent segment' => ['../a'];
        yield 'unix absolute' => ['/a'];
        yield 'windows drive' => ['C:/a'];
        yield 'UNC slashes' => ['//server/share'];
        yield 'UNC backslashes' => ['\\\\server\\share'];
        yield 'backslash' => ['a\\b'];
        yield 'control character' => ["a/\x1Fb"];
        yield 'leading separator' => ['/relative'];
        yield 'trailing separator' => ['relative/'];
    }

    #[DataProvider('invalidRelativePaths')]
    public function testCanonicalRelativePathRejectsInvalidRepresentation(string $path): void
    {
        $this->expectException(RuntimeException::class);
        RelativePath::canonical($path);
    }

    public function testCanonicalRelativePathAcceptsProductionPaths(): void
    {
        self::assertSame('public_html/index.php', RelativePath::canonical('public_html/index.php'));
        self::assertSame(
            'vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php',
            RelativePath::canonical('vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php')
        );
    }

    public function testDistributionManifestRejectsExactDuplicateWithoutUsingManifestWriter(): void
    {
        $root = $this->workspace . '/duplicate-manifest';
        self::assertTrue(mkdir($root, 0755, true));
        $hash = hash('sha256', 'same');
        $this->writeFile($root . '/' . ManagedFileManifest::FILENAME, json_encode([
            'format' => 2,
            'application_version' => UpdateService::VERSION,
            'files' => [
                ['path' => 'same.txt', 'sha256' => $hash],
                ['path' => 'same.txt', 'sha256' => $hash],
            ],
        ], JSON_PRETTY_PRINT) ?: '{}');

        $this->expectException(RuntimeException::class);
        ManagedFileManifest::load($root);
    }

    public function testDistributionManifestRejectsNonCanonicalCollision(): void
    {
        $root = $this->workspace . '/collision-manifest';
        self::assertTrue(mkdir($root, 0755, true));
        $hash = hash('sha256', 'same');
        $this->writeFile($root . '/' . ManagedFileManifest::FILENAME, json_encode([
            'format' => 2,
            'application_version' => UpdateService::VERSION,
            'files' => [
                ['path' => 'a/b.txt', 'sha256' => $hash],
                ['path' => 'a/./b.txt', 'sha256' => $hash],
            ],
        ], JSON_PRETTY_PRINT) ?: '{}');

        $this->expectException(RuntimeException::class);
        ManagedFileManifest::load($root);
    }

    /** @return iterable<string,array{string,int}> */
    public static function invalidZipEntries(): iterable
    {
        yield 'dot segment' => ['a/./b', 0100644];
        yield 'duplicate separator' => ['a//b', 0100644];
        yield 'parent segment' => ['../a', 0100644];
        yield 'unix absolute' => ['/a', 0100644];
        yield 'windows drive' => ['C:/a', 0100644];
        yield 'UNC' => ['//server/share', 0100644];
        yield 'backslash' => ['a\\b', 0100644];
        yield 'trailing separator on regular file' => ['a/', 0100644];
        yield 'symlink' => ['link', 0120777];
        yield 'special type' => ['device', 0020666];
        yield 'unknown attributes' => ['unknown', 0];
    }

    #[DataProvider('invalidZipEntries')]
    public function testZipRejectsInvalidNamesAndTypes(string $name, int $mode): void
    {
        $zipFile = $this->workspace . '/invalid-' . md5($name . ':' . $mode) . '.zip';
        $this->writeRawZip($zipFile, [['name' => $name, 'contents' => 'payload', 'mode' => $mode]]);
        $outside = $this->workspace . '/outside-witness.txt';
        $this->writeFile($outside, 'untouched');

        $this->expectException(RuntimeException::class);
        try {
            $this->inspectZip($zipFile);
        } finally {
            self::assertSame('untouched', file_get_contents($outside));
        }
    }

    public function testZipRejectsExactDuplicateAndDirectoryFileCollision(): void
    {
        $collisions = [
            [
                ['name' => 'same.txt', 'contents' => 'one', 'mode' => 0100644],
                ['name' => 'same.txt', 'contents' => 'two', 'mode' => 0100644],
            ],
            [
                ['name' => 'same/', 'contents' => '', 'mode' => 0040755],
                ['name' => 'same', 'contents' => 'file', 'mode' => 0100644],
            ],
        ];
        foreach ($collisions as $index => $entries) {
            $zipFile = $this->workspace . "/collision-{$index}.zip";
            $this->writeRawZip($zipFile, $entries);
            try {
                $this->inspectZip($zipFile);
                self::fail('La colisión del ZIP no fue rechazada');
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testZipAcceptsRegularFilesDirectoriesVendorAndAllowedMetadata(): void
    {
        $zipFile = $this->workspace . '/valid.zip';
        $this->writeRawZip($zipFile, [
            ['name' => 'vendor/', 'contents' => '', 'mode' => 0040755],
            [
                'name' => 'vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php',
                'contents' => 'menu',
                'mode' => 0100644,
            ],
            ['name' => ManagedFileManifest::FILENAME, 'contents' => '{}', 'mode' => 0100644],
        ]);

        self::assertSame([
            'vendor',
            'vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php',
            ManagedFileManifest::FILENAME,
        ], $this->inspectZip($zipFile));
    }

    public function testZipRejectsProtectedEntriesBeforeExtraction(): void
    {
        $protectedPaths = [
            '.htaccess',
            'public_html/.htaccess',
            'Content/private.txt',
            'storage/private.txt',
            'var/cache/file.php',
            'public_html/uploads/photo.jpg',
        ];
        foreach ($protectedPaths as $index => $relative) {
            $zipFile = $this->workspace . "/protected-{$index}.zip";
            $this->writeRawZip($zipFile, [[
                'name' => $relative,
                'contents' => 'protected',
                'mode' => 0100644,
            ]]);
            try {
                $this->inspectZip($zipFile);
                self::fail("La ruta protegida no fue rechazada: {$relative}");
            } catch (RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testInvalidArtifactDoesNotModifyInstallation(): void
    {
        $release = $this->validReleaseFixture('invalid-install');
        $this->writeFile($release . '/Content/private.txt', 'protected');
        $install = $this->workspace . '/untouched-install';
        $this->writeFile($install . '/witness.txt', 'untouched');

        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('El artefacto inválido no fue rechazado');
        } catch (RuntimeException) {
            self::assertSame('untouched', file_get_contents($install . '/witness.txt'));
            self::assertFileDoesNotExist($install . '/composer.lock');
        }
    }

    public function testAssetManifestRejectsDuplicatesAndNonCanonicalPaths(): void
    {
        $invalidManifests = [
            [
                ['path' => 'default/css/a.css', 'sha256' => hash('sha256', 'a')],
                ['path' => 'default/css/a.css', 'sha256' => hash('sha256', 'a')],
            ],
            [
                ['path' => 'default/css/./a.css', 'sha256' => hash('sha256', 'a')],
            ],
        ];
        foreach ($invalidManifests as $index => $entries) {
            $app = $this->workspace . "/invalid-assets-{$index}";
            $this->writeFile(
                $app . '/vendor/alxarafe/alxarafe/templates/themes/default/css/default.css',
                'asset'
            );
            $this->writeFile(
                $app . '/public_html/themes/.chascarrillo-theme-assets.json',
                json_encode(['format' => 2, 'files' => $entries], JSON_PRETTY_PRINT) ?: '{}'
            );
            try {
                (new ThemeAssetPublisher())->publish($app);
                self::fail('El manifiesto de assets inseguro no fue rechazado');
            } catch (RuntimeException) {
                self::assertFileDoesNotExist($app . '/public_html/themes/default/css/default.css');
            }
        }
    }

    /** @return iterable<string,array{string}> */
    public static function forbiddenArtifactFiles(): iterable
    {
        yield 'root htaccess' => ['.htaccess'];
        yield 'public htaccess' => ['public_html/.htaccess'];
        yield 'content' => ['Content/private.txt'];
        yield 'storage' => ['storage/private.txt'];
        yield 'var' => ['var/cache/file.php'];
        yield 'uploads' => ['public_html/uploads/photo.jpg'];
        yield 'unmanaged extra' => ['unexpected-local-report.txt'];
    }

    #[DataProvider('forbiddenArtifactFiles')]
    public function testStrictArtifactRejectsProtectedAndUnmanagedFiles(string $relative): void
    {
        $root = $this->validReleaseFixture('forbidden-' . md5($relative));
        $this->writeFile($root . '/' . $relative, 'must not ship');

        $this->expectException(RuntimeException::class);
        (new ReleaseValidator())->validate($root, true, true);
    }

    public function testStrictArtifactAcceptsOnlyManifestFilesAndExplicitMetadata(): void
    {
        $root = $this->validReleaseFixture('valid-artifact');
        $result = (new ReleaseValidator())->validate($root, true, true);

        self::assertSame('v0.6.11', $result['version']);
        self::assertFileExists($root . '/' . ManagedFileManifest::FILENAME);
    }

    /** @return iterable<string,array{string|null,string}> */
    public static function invalidApplicationVersions(): iterable
    {
        yield 'missing' => [null, 'application_version'];
        yield 'invalid format' => ['release-0.8.17', 'formato'];
        yield 'canonical mismatch' => ['9.9.9', 'no coincide'];
    }

    #[DataProvider('invalidApplicationVersions')]
    public function testReleaseRejectsInvalidApplicationVersion(?string $version, string $message): void
    {
        $root = $this->validReleaseFixture('invalid-version-' . md5((string) $version));
        $manifestFile = $root . '/' . ManagedFileManifest::FILENAME;
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        self::assertIsArray($manifest);
        if ($version === null) {
            unset($manifest['application_version']);
        } else {
            $manifest['application_version'] = $version;
        }
        $this->writeFile($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT) ?: '{}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new ReleaseValidator())->validate($root, true, true);
    }

    public function testVersionMismatchIsRejectedBeforeInstallationChanges(): void
    {
        $release = $this->validReleaseFixture('mismatched-version-install');
        $manifestFile = $release . '/' . ManagedFileManifest::FILENAME;
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        self::assertIsArray($manifest);
        $manifest['application_version'] = '9.9.9';
        $this->writeFile($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT) ?: '{}');
        $install = $this->workspace . '/version-untouched-install';
        $this->writeFile($install . '/witness.txt', 'untouched');

        try {
            (new ReleaseInstaller())->install($release, $install);
            self::fail('La versión incoherente no fue rechazada');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('no coincide', $exception->getMessage());
            self::assertSame('untouched', file_get_contents($install . '/witness.txt'));
            self::assertFileDoesNotExist($install . '/composer.lock');
        }
    }

    public function testLegacyVersionFieldIsAcceptedOnlyForPreviouslyInstalledManifest(): void
    {
        $root = $this->workspace . '/legacy-installed-manifest';
        $this->writeFile($root . '/managed.txt', 'legacy');
        $this->writeFile($root . '/' . ManagedFileManifest::FILENAME, json_encode([
            'format' => 2,
            'version' => 'v0.8.17',
            'files' => [[
                'path' => 'managed.txt',
                'sha256' => hash('sha256', 'legacy'),
            ]],
        ], JSON_PRETTY_PRINT) ?: '{}');

        $legacy = ManagedFileManifest::load($root, true, true);
        self::assertSame('', $legacy['application_version']);
        self::assertArrayHasKey('managed.txt', $legacy['files']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('application_version');
        ManagedFileManifest::load($root);
    }

    /** @return list<string> */
    private function inspectZip(string $filename): array
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($filename));
        try {
            return (new ReleaseArchiveValidator())->validate($zip);
        } finally {
            $zip->close();
        }
    }

    private function validReleaseFixture(string $name): string
    {
        $root = $this->workspace . '/' . $name;
        $project = dirname(__DIR__, 2);
        $files = [];
        $files['Modules/Chascarrillo/Service/UpdateService.php'] = (string) file_get_contents(
            $project . '/Modules/Chascarrillo/Service/UpdateService.php'
        );
        $files['composer.lock'] = (string) file_get_contents($project . '/composer.lock');
        $files['vendor/composer/installed.json'] = json_encode([
            'packages' => [[
                'name' => 'alxarafe/alxarafe',
                'version' => 'v0.6.11',
                'source' => ['reference' => '4b5a6252750537280c04aa4378b3fcf2570f8efb'],
            ]],
        ], JSON_PRETTY_PRINT) ?: '{}';
        foreach (ReleaseValidator::ESSENTIAL_TEMPLATES as $relative) {
            $files[$relative] = $relative === 'vendor/alxarafe/alxarafe/templates/partial/user_menu.blade.php'
                ? 'clock-display controller=Auth partial.lang_switcher partial.theme_switcher Auth::$user'
                : 'template';
        }
        $files['public_html/index.php'] = '<?php // fixture';

        $manifestFiles = [];
        foreach ($files as $relative => $contents) {
            $this->writeFile($root . '/' . $relative, $contents);
            $manifestFiles[] = ['path' => $relative, 'sha256' => hash('sha256', $contents)];
        }
        usort($manifestFiles, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);
        $this->writeFile($root . '/' . ManagedFileManifest::FILENAME, json_encode([
            'format' => 2,
            'application_version' => UpdateService::VERSION,
            'files' => $manifestFiles,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        return $root;
    }

    /**
     * @param list<array{name:string,contents:string,mode:int}> $entries
     */
    private function writeRawZip(string $filename, array $entries): void
    {
        $local = '';
        $central = '';
        foreach ($entries as $entry) {
            $name = $entry['name'];
            $contents = $entry['contents'];
            $offset = strlen($local);
            $crc = crc32($contents);
            $size = strlen($contents);
            $local .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0)
                . $name . $contents;
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                0x0314,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                strlen($name),
                0,
                0,
                0,
                0,
                $entry['mode'] << 16,
                $offset
            ) . $name;
        }
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), strlen($local), 0);
        $this->writeFile($filename, $local . $central . $end);
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
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->removeTree($path . '/' . $entry);
        }
        rmdir($path);
    }
}
