<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Indicators;

use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodResolver;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CurrencyTotals;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\PeriodStatistics;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\PurchaseStatisticsInterface;

final readonly class DashboardProvider
{
    public function __construct(
        private PeriodResolver $periodResolver,
        private PurchaseStatisticsInterface $statistics,
    ) {
    }

    /** @param string $fallbackCurrency Currency of the zero amounts of a company without order */
    public function provide(int $companyId, PeriodKey $key, string $fallbackCurrency): Dashboard
    {
        $periods = $this->periodResolver->resolve($key);
        $current = $this->statistics->counted($companyId, $periods->current);
        $previous = $this->statistics->counted($companyId, $periods->previous);

        return new Dashboard(
            $periods,
            Indicator::compare($current->ordersCount(), $previous->ordersCount()),
            Indicator::compare($current->activeBuyers, $previous->activeBuyers),
            $this->currencies($current, $previous, $fallbackCurrency),
            $this->statistics->pending($companyId, $periods->current),
        );
    }

    /** @return list<CurrencyIndicators> */
    private function currencies(PeriodStatistics $current, PeriodStatistics $previous, string $fallbackCurrency): array
    {
        $codes = array_unique([...array_keys($current->totals), ...array_keys($previous->totals)]);
        sort($codes);
        if ([] === $codes) {
            $codes = [$fallbackCurrency];
        }

        return array_map(static function (string $code) use ($current, $previous): CurrencyIndicators {
            $now = $current->totals[$code] ?? new CurrencyTotals($code, 0, 0);
            $before = $previous->totals[$code] ?? new CurrencyTotals($code, 0, 0);

            return new CurrencyIndicators(
                $code,
                Indicator::compare($now->spend, $before->spend),
                Indicator::compare($now->averageBasket(), $before->averageBasket()),
            );
        }, $codes);
    }
}
