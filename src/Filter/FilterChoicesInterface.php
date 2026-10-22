<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Filter;

/** The members and the delivery sites a company can be filtered on: the allow-lists of the filters. */
interface FilterChoicesInterface
{
    /** @return list<MemberChoice> */
    public function members(int $companyId): array;

    /** @return list<SiteChoice> */
    public function sites(int $companyId): array;
}
