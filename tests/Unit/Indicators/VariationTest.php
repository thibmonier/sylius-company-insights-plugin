<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Indicators;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\Trend;
use Tmonier\SyliusCompanyInsightsPlugin\Indicators\Variation;

final class VariationTest extends TestCase
{
    public function testAnIncreaseIsAPositivePercentage(): void
    {
        $variation = Variation::between(120, 100);

        self::assertSame(20.0, $variation->percent);
        self::assertSame(Trend::Up, $variation->trend);
    }

    public function testADecreaseIsANegativePercentage(): void
    {
        $variation = Variation::between(75, 100);

        self::assertSame(-25.0, $variation->percent);
        self::assertSame(Trend::Down, $variation->trend);
    }

    public function testNoChangeIsFlat(): void
    {
        $variation = Variation::between(100, 100);

        self::assertSame(0.0, $variation->percent);
        self::assertSame(Trend::Flat, $variation->trend);
    }

    public function testThePercentageIsRoundedToOneDecimal(): void
    {
        self::assertSame(33.3, Variation::between(400, 300)->percent);
        self::assertSame(-66.7, Variation::between(100, 300)->percent);
    }

    public function testAVariationFromZeroCannotBeComputed(): void
    {
        $variation = Variation::between(500, 0);

        self::assertNull($variation->percent);
        self::assertSame(Trend::Unknown, $variation->trend);
    }

    public function testTwoEmptyPeriodsHaveNoVariation(): void
    {
        $variation = Variation::between(0, 0);

        self::assertNull($variation->percent);
        self::assertSame(Trend::Unknown, $variation->trend);
    }

    public function testADropToZeroIsMinusOneHundredPercent(): void
    {
        $variation = Variation::between(0, 250);

        self::assertSame(-100.0, $variation->percent);
        self::assertSame(Trend::Down, $variation->trend);
    }
}
