<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/** How the products are ranked. The values are the `sort` query parameter (allow-list). */
enum ProductSort: string
{
    case Spend = 'spend';
    case Quantity = 'quantity';

    public static function default(): self
    {
        return self::Spend;
    }

    /** Anything that is not exactly a known sort (missing, unknown, array...) is the default one. */
    public static function fromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::default()) : self::default();
    }

    /** Translation key of the label of the sort. */
    public function label(): string
    {
        return 'tmonier_company_insights.ranking.sort.' . $this->value;
    }

    /** A fixed SQL fragment, never built from the request; the code makes the order deterministic. */
    public function orderBy(): string
    {
        return match ($this) {
            self::Spend => 'spend DESC, quantity DESC, v.code ASC',
            self::Quantity => 'quantity DESC, spend DESC, v.code ASC',
        };
    }
}
