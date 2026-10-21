<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

interface BuyerRankingInterface
{
    /** @return list<BuyerRow> by decreasing spend */
    public function rows(RankingScope $scope, PageRequest $page): array;

    public function totals(RankingScope $scope): RankingTotals;
}
