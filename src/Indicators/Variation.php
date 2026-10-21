<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Indicators;

/** Change of an indicator against the previous period, in percent (one decimal). */
final readonly class Variation
{
    private function __construct(
        public ?float $percent,
        public Trend $trend,
    ) {
    }

    public static function between(int $current, int $previous): self
    {
        if (0 === $previous) {
            return new self(null, Trend::Unknown);
        }

        $percent = round(($current - $previous) / $previous * 100, 1);

        return new self($percent, match (true) {
            $percent > 0 => Trend::Up,
            $percent < 0 => Trend::Down,
            default => Trend::Flat,
        });
    }
}
