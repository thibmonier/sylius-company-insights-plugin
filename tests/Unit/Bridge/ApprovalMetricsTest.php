<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Bridge;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval\ApprovalMetrics;

final class ApprovalMetricsTest extends TestCase
{
    /** @param list<int> $values */
    #[DataProvider('medians')]
    public function testTheMedianIsTheMiddleValueOrTheMeanOfTheTwoMiddleOnes(array $values, ?int $expected): void
    {
        self::assertSame($expected, ApprovalMetrics::median($values));
    }

    /** @return iterable<string, array{list<int>, ?int}> */
    public static function medians(): iterable
    {
        yield 'nothing' => [[], null];
        yield 'one value' => [[7200], 7200];
        yield 'odd count, unsorted' => [[36000, 7200, 14400], 14400];
        yield 'even count' => [[3600, 7200], 5400];
        yield 'an outlier does not move it' => [[3600, 3600, 3600, 3_600_000], 3600];
    }

    public function testThePercentIsRoundedAndNeverAboveOneHundred(): void
    {
        self::assertSame(33, ApprovalMetrics::percent(1, 3));
        self::assertSame(67, ApprovalMetrics::percent(2, 3));
        self::assertSame(100, ApprovalMetrics::percent(5, 4));
        self::assertSame(0, ApprovalMetrics::percent(0, 4));
        self::assertNull(ApprovalMetrics::percent(0, 0));
    }
}
