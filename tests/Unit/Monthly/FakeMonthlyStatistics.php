<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Monthly;

use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlyPurchaseStatisticsInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\MonthlyTotals;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

final class FakeMonthlyStatistics implements MonthlyPurchaseStatisticsInterface
{
    public ?int $companyId = null;

    /** @var list<Period> */
    public array $months = [];

    /** @param list<MonthlyTotals> $totals */
    public function __construct(private readonly array $totals)
    {
    }

    public function perMonth(int $companyId, array $months): array
    {
        $this->companyId = $companyId;
        $this->months = $months;

        return $this->totals;
    }
}
