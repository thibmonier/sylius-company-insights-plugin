<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Monthly;

/** What the monthly chart shows. The values are the `metric` query parameter (allow-list). */
enum Metric: string
{
    case Amount = 'amount';
    case Orders = 'orders';

    public static function default(): self
    {
        return self::Amount;
    }

    /** Anything that is not exactly a known metric (missing, unknown, array...) is the default one. */
    public static function fromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::default()) : self::default();
    }

    /** Translation key of the label of the metric. */
    public function label(): string
    {
        return 'tmonier_company_insights.metric.' . $this->value;
    }
}
