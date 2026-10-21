<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

interface CategoryRankingInterface
{
    /**
     * Every main category, not a page of them: there are few, and their shares must add up to 100.
     *
     * @return list<CategoryRow> by decreasing spend
     */
    public function rows(RankingScope $scope): array;
}
