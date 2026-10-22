<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Widget;

use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodComparison;

/** What a widget may know: the company and the member signed in, the period shown, the filter, the locale. */
final readonly class WidgetContext
{
    public function __construct(
        public CompanyInterface $company,
        public CompanyMemberInterface $member,
        public PeriodComparison $periods,
        public PurchaseFilter $filter,
        public string $localeCode,
    ) {
    }
}
