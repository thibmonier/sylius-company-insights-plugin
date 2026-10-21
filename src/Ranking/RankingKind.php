<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/** The rankings of the dashboard. The values are the `{ranking}` segment of the "see all" pages (allow-list). */
enum RankingKind: string
{
    case Buyers = 'buyers';
    case Products = 'products';
    case Categories = 'categories';

    /** Translation key of the title of the ranking. */
    public function title(): string
    {
        return 'tmonier_company_insights.ranking.' . $this->value . '.title';
    }

    /** Pattern of the route requirement, made of the values of the enum. */
    public static function routeRequirement(): string
    {
        return implode('|', array_map(static fn (self $kind): string => $kind->value, self::cases()));
    }
}
