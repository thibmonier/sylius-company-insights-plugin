<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingScope;

interface SiteBreakdownInterface
{
    /** @return list<BreakdownRow> one per delivery address; the label is that of the address book of the company, null when the address is not in it */
    public function rows(RankingScope $scope): array;
}
