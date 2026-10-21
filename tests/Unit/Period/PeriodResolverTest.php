<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Period;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodComparison;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodResolver;

final class PeriodResolverTest extends TestCase
{
    /**
     * @param array{string, string} $current
     * @param array{string, string} $previous
     */
    #[DataProvider('periods')]
    public function testItResolvesThePeriodAndThePreviousOneOfTheSameDuration(string $now, PeriodKey $key, array $current, array $previous): void
    {
        $comparison = (new PeriodResolver(new MockClock($now)))->resolve($key);

        self::assertSame($current, $this->bounds($comparison->current));
        self::assertSame($previous, $this->bounds($comparison->previous));
        if (PeriodKey::Last12Months !== $key) {
            // Twelve calendar months do not always hold the same number of days.
            self::assertSame($comparison->current->days(), $comparison->previous->days());
        }
    }

    /** @return iterable<string, array{string, PeriodKey, array{string, string}, array{string, string}}> */
    public static function periods(): iterable
    {
        // Upper bounds are exclusive: "today" is part of the period, so the bound is tomorrow 00:00.
        yield 'last 30 days' => ['2026-10-21 15:30:00', PeriodKey::Last30Days, ['2026-09-22', '2026-10-22'], ['2026-08-23', '2026-09-22']];
        yield 'quarter to date' => ['2026-10-21 15:30:00', PeriodKey::CurrentQuarter, ['2026-10-01', '2026-10-22'], ['2026-07-01', '2026-07-22']];
        yield 'last 12 months' => ['2026-10-21 15:30:00', PeriodKey::Last12Months, ['2025-10-22', '2026-10-22'], ['2024-10-22', '2025-10-22']];
        yield 'year to date' => ['2026-10-21 15:30:00', PeriodKey::CurrentYear, ['2026-01-01', '2026-10-22'], ['2025-01-01', '2025-10-22']];
        yield 'first day of the year' => ['2026-01-01 00:00:00', PeriodKey::CurrentYear, ['2026-01-01', '2026-01-02'], ['2025-01-01', '2025-01-02']];
        yield 'first quarter' => ['2026-02-10 09:00:00', PeriodKey::CurrentQuarter, ['2026-01-01', '2026-02-11'], ['2025-10-01', '2025-11-11']];
        yield 'last day of the year' => ['2026-12-31 23:59:59', PeriodKey::CurrentYear, ['2026-01-01', '2027-01-01'], ['2025-01-01', '2026-01-01']];
        yield 'leap day' => ['2028-02-29 12:00:00', PeriodKey::Last12Months, ['2027-03-01', '2028-03-01'], ['2026-03-01', '2027-03-01']];
    }

    public function testThePreviousPeriodStartsExactlyWhereTheRollingPeriodStarts(): void
    {
        $comparison = (new PeriodResolver(new MockClock('2026-10-21 15:30:00')))->resolve(PeriodKey::Last30Days);

        self::assertEquals($comparison->current->from, $comparison->previous->to);
    }

    public function testItUsesTheTimezoneOfTheClock(): void
    {
        $clock = (new MockClock('2026-10-21 23:30:00', 'UTC'))->withTimeZone('Asia/Tokyo');

        $comparison = (new PeriodResolver($clock))->resolve(PeriodKey::Last30Days);

        // 2026-10-22 08:30 in Tokyo: the period ends at the next Tokyo midnight.
        self::assertSame(['2026-09-23', '2026-10-23'], $this->bounds($comparison->current));
    }

    /** @return array{string, string} */
    private function bounds(\Tmonier\SyliusCompanyInsightsPlugin\Period\Period $period): array
    {
        return [$period->from->format('Y-m-d'), $period->to->format('Y-m-d')];
    }

    public function testItReturnsAComparison(): void
    {
        self::assertInstanceOf(PeriodComparison::class, (new PeriodResolver(new MockClock('2026-10-21')))->resolve(PeriodKey::default()));
    }
}
