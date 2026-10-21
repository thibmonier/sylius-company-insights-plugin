<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Period;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;

final class PeriodKeyTest extends TestCase
{
    public function testTheDefaultPeriodIsTheLastTwelveMonths(): void
    {
        self::assertSame(PeriodKey::Last12Months, PeriodKey::default());
    }

    #[DataProvider('knownKeys')]
    public function testAKnownKeyIsAccepted(string $value, PeriodKey $expected): void
    {
        self::assertSame($expected, PeriodKey::fromRequest($value));
    }

    /** @return iterable<string, array{string, PeriodKey}> */
    public static function knownKeys(): iterable
    {
        yield 'thirty days' => ['last_30_days', PeriodKey::Last30Days];
        yield 'quarter' => ['current_quarter', PeriodKey::CurrentQuarter];
        yield 'twelve months' => ['last_12_months', PeriodKey::Last12Months];
        yield 'year' => ['current_year', PeriodKey::CurrentYear];
    }

    #[DataProvider('unknownValues')]
    public function testAnythingElseFallsBackToTheDefault(mixed $value): void
    {
        self::assertSame(PeriodKey::default(), PeriodKey::fromRequest($value));
    }

    /** @return iterable<string, array{mixed}> */
    public static function unknownValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'unknown key' => ['last_week'];
        yield 'sql' => ["last_30_days' OR 1=1 --"];
        yield 'array' => [['last_30_days']];
        yield 'int' => [30];
        yield 'enum case name' => ['Last30Days'];
    }
}
