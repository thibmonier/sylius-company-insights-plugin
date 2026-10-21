<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

interface ProductRankingInterface
{
    /** @return list<ProductRow> */
    public function rows(RankingScope $scope, ProductSort $sort, PageRequest $page): array;

    public function totals(RankingScope $scope): RankingTotals;
}
