<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Export;

use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

/** The company and the member come from the signed-in member, never from the request. */
final readonly class ExportRequest
{
    public function __construct(
        public int $companyId,
        public ?int $memberId,
        public Period $period,
        public PurchaseFilter $filter,
        public string $localeCode,
    ) {
    }
}
