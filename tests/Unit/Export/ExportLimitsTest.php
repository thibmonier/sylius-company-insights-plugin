<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Export;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Export\ExportLimits;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

final class ExportLimitsTest extends TestCase
{
    #[DataProvider('periods')]
    public function testTheLimitIsExactlyTwentyFourCalendarMonths(string $from, string $to, bool $allowed): void
    {
        self::assertSame($allowed, ExportLimits::allowsPeriod(new Period(new \DateTimeImmutable($from), new \DateTimeImmutable($to))));
    }

    public static function periods(): iterable
    {
        yield 'a regular day, on the bound' => ['2024-10-01', '2026-10-01', true];
        yield 'a regular day, one day over' => ['2024-10-01', '2026-10-02', false];
        yield 'the 29 February, on the bound (28 February two years later)' => ['2024-02-29', '2026-02-28', true];
        yield 'the 29 February, one day over (no overflow to 1 March)' => ['2024-02-29', '2026-03-01', false];
        yield 'the 29 February of a leap year after a leap year' => ['2024-02-29', '2028-02-29', false];
        yield 'the 31st, on the bound' => ['2024-08-31', '2026-08-31', true];
        yield 'the 31st before a short month' => ['2024-12-31', '2026-12-31', true];
        yield 'with a time of day, on the bound' => ['2024-02-29 10:30:00', '2026-02-28 10:30:00', true];
        yield 'with a time of day, one second over' => ['2024-02-29 10:30:00', '2026-02-28 10:30:01', false];
    }
}
