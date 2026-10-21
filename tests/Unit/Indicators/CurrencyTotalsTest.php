<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Indicators;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CurrencyTotals;

final class CurrencyTotalsTest extends TestCase
{
    public function testTheAverageBasketIsTheSpendDividedByTheNumberOfOrders(): void
    {
        self::assertSame(70_000, (new CurrencyTotals('EUR', 6, 420_000))->averageBasket());
    }

    public function testTheAverageBasketIsRoundedToTheMinorUnit(): void
    {
        self::assertSame(3_334, (new CurrencyTotals('EUR', 3, 10_000))->averageBasket());
    }

    public function testWithoutOrderTheAverageBasketIsZero(): void
    {
        self::assertSame(0, (new CurrencyTotals('EUR', 0, 0))->averageBasket());
    }
}
