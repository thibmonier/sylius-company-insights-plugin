<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Period;

final readonly class PeriodComparison
{
    public function __construct(
        public PeriodKey $key,
        public Period $current,
        public Period $previous,
    ) {
    }
}
