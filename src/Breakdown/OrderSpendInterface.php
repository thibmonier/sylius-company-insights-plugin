<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingScope;

interface OrderSpendInterface
{
    /** @return array<int, int> id => tax-exclusive spend, of the counted orders of the scope */
    public function perOrder(RankingScope $scope): array;
}
