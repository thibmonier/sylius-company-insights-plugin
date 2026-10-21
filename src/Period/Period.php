<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Period;

/** From (included) to (excluded). */
final readonly class Period
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {
    }

    /** Number of whole days between the bounds. */
    public function days(): int
    {
        return (int) $this->from->diff($this->to)->format('%a');
    }

    /** The last day included in the period (the upper bound is exclusive). */
    public function lastDay(): \DateTimeImmutable
    {
        return $this->to->modify('-1 day');
    }
}
