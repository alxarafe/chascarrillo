<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Chascarrillo\Service\ApplicationVersion;
use Modules\Chascarrillo\Service\UpdateService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationVersionTest extends TestCase
{
    public function testCanonicalVersionIsValid(): void
    {
        self::assertSame(UpdateService::VERSION, ApplicationVersion::canonical());
    }

    /** @return iterable<string,array{string}> */
    public static function matchingTags(): iterable
    {
        yield 'without prefix' => [UpdateService::VERSION];
        yield 'with conventional prefix' => ['v' . UpdateService::VERSION];
    }

    #[DataProvider('matchingTags')]
    public function testMatchingTagIsAccepted(string $tag): void
    {
        ApplicationVersion::assertTagMatches($tag, UpdateService::VERSION);
        self::addToAssertionCount(1);
    }

    public function testMismatchingTagIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tag');
        ApplicationVersion::assertTagMatches('v9.9.9', UpdateService::VERSION);
    }

    public function testLocalValidationDoesNotRequireTag(): void
    {
        ApplicationVersion::assertTagMatches(null, UpdateService::VERSION);
        self::addToAssertionCount(1);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidVersions(): iterable
    {
        yield 'prefixed application version' => ['v0.8.17'];
        yield 'leading whitespace' => [' 0.8.17'];
        yield 'partial version' => ['0.8'];
        yield 'double tag prefix' => ['vv0.8.17'];
    }

    #[DataProvider('invalidVersions')]
    public function testInvalidApplicationVersionIsRejected(string $version): void
    {
        $this->expectException(RuntimeException::class);
        ApplicationVersion::assertValid($version, 'versión de prueba');
    }
}
