<?php

declare(strict_types=1);

namespace Modules\Chascarrillo\Service;

use RuntimeException;

final class ApplicationVersion
{
    private const PATTERN = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)'
        . '(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/';

    public static function canonical(): string
    {
        self::assertValid(UpdateService::VERSION, 'UpdateService::VERSION');
        return UpdateService::VERSION;
    }

    public static function fromRoot(string $root): string
    {
        $filename = rtrim($root, '/') . '/Modules/Chascarrillo/Service/UpdateService.php';
        $source = is_file($filename) ? file_get_contents($filename) : false;
        if ($source === false) {
            throw new RuntimeException('El artefacto no contiene la fuente canónica UpdateService.php');
        }

        $tokens = token_get_all($source);
        $count = count($tokens);
        $braceDepth = 0;
        $classDepth = null;
        $waitingForClassBrace = false;
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '{') {
                $braceDepth++;
                if ($waitingForClassBrace) {
                    $classDepth = $braceDepth;
                    $waitingForClassBrace = false;
                }
                continue;
            }
            if ($token === '}') {
                if ($classDepth === $braceDepth) {
                    $classDepth = null;
                }
                $braceDepth--;
                continue;
            }
            if (is_array($token) && $token[0] === T_CLASS) {
                $className = self::nextMeaningfulToken($tokens, $index + 1);
                $waitingForClassBrace = is_array($className['token'])
                    && $className['token'][0] === T_STRING
                    && $className['token'][1] === 'UpdateService';
                $index = $className['index'];
                continue;
            }
            if ($classDepth === null || !is_array($token) || $token[0] !== T_CONST) {
                continue;
            }
            $name = self::nextMeaningfulToken($tokens, ++$index);
            if (!is_array($name['token']) || $name['token'][0] !== T_STRING || $name['token'][1] !== 'VERSION') {
                $index = $name['index'];
                continue;
            }
            $equals = self::nextMeaningfulToken($tokens, $name['index'] + 1);
            $literal = self::nextMeaningfulToken($tokens, $equals['index'] + 1);
            if (
                $equals['token'] !== '=' || !is_array($literal['token'])
                || $literal['token'][0] !== T_CONSTANT_ENCAPSED_STRING
            ) {
                break;
            }
            $quoted = $literal['token'][1];
            if (
                strlen($quoted) < 2 || !in_array($quoted[0], ["'", '"'], true)
                || $quoted[0] !== $quoted[strlen($quoted) - 1] || str_contains($quoted, '\\')
            ) {
                break;
            }
            $version = substr($quoted, 1, -1);
            self::assertValid($version, 'UpdateService::VERSION del artefacto');
            return $version;
        }
        throw new RuntimeException('No se pudo leer UpdateService::VERSION del artefacto');
    }

    public static function assertValid(string $version, string $label): void
    {
        if (preg_match(self::PATTERN, $version) !== 1) {
            throw new RuntimeException("{$label} tiene un formato no válido: {$version}");
        }
    }

    public static function assertManifestMatches(string $manifestVersion, string $canonicalVersion): void
    {
        self::assertValid($manifestVersion, 'application_version del manifiesto');
        self::assertValid($canonicalVersion, 'UpdateService::VERSION del artefacto');
        if ($manifestVersion !== $canonicalVersion) {
            throw new RuntimeException(
                "application_version del manifiesto ({$manifestVersion}) no coincide "
                . "con UpdateService::VERSION ({$canonicalVersion})"
            );
        }
    }

    public static function assertTagMatches(?string $tag, string $expectedVersion): void
    {
        if ($tag === null) {
            return;
        }
        $tagVersion = str_starts_with($tag, 'v') ? substr($tag, 1) : $tag;
        self::assertValid($tagVersion, 'La versión normalizada del tag');
        if ($tagVersion !== $expectedVersion) {
            throw new RuntimeException(
                "La versión del tag ({$tag}) no coincide con la versión de aplicación ({$expectedVersion})"
            );
        }
    }

    /**
     * @param list<array{int,string,int}|string> $tokens
     * @return array{index:int,token:array{int,string,int}|string|null}
     */
    private static function nextMeaningfulToken(array $tokens, int $index): array
    {
        $count = count($tokens);
        while ($index < $count) {
            $token = $tokens[$index];
            if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return ['index' => $index, 'token' => $token];
            }
            $index++;
        }
        return ['index' => $index, 'token' => null];
    }
}
