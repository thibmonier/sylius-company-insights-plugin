<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Ranking;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\Shares;

final class SharesTest extends TestCase
{
    /**
     * @param list<int> $amounts
     * @param list<int> $expected
     */
    #[DataProvider('provideSharesThatAddUpToTheTotal')]
    public function testTheSharesOfAWholeAddUpToOneHundred(array $amounts, array $expected): void
    {
        $shares = Shares::distribute($amounts, array_sum($amounts));

        self::assertSame($expected, $shares);
        self::assertSame(100, array_sum($shares));
    }

    /** @return iterable<string, array{list<int>, list<int>}> */
    public static function provideSharesThatAddUpToTheTotal(): iterable
    {
        yield 'three equal parts' => [[10_000, 10_000, 10_000], [34, 33, 33]];
        yield 'two buyers' => [[260_000, 160_000], [62, 38]];
        yield 'one part' => [[5_000], [100]];
        yield 'a tiny part' => [[99_999, 1], [100, 0]];
        yield 'halves' => [[1, 1], [50, 50]];
    }

    public function testNothingSpentGivesZeros(): void
    {
        self::assertSame([0, 0], Shares::distribute([0, 0], 0));
        self::assertSame([], Shares::distribute([], 0));
    }

    public function testAPartOfTheTotalIsRoundedToTheNearestPercent(): void
    {
        self::assertSame(62, Shares::percent(260_000, 420_000));
        self::assertSame(38, Shares::percent(160_000, 420_000));
        self::assertSame(0, Shares::percent(10, 0));
    }
}
