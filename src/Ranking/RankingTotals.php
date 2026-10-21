<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/** The whole of a ranking: its number of rows and the spend they add up to. */
final readonly class RankingTotals
{
    public function __construct(
        public int $rows,
        public int $spend,
    ) {
    }
}
