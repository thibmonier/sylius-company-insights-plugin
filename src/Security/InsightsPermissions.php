<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Security;

/** Security attribute of the purchasing dashboard: `is_granted('COMPANY_VIEW_INSIGHTS', company)`. */
final class InsightsPermissions
{
    /** Subject: company. An enabled administrator of the enabled company. */
    public const COMPANY_VIEW_INSIGHTS = 'COMPANY_VIEW_INSIGHTS';

    private function __construct()
    {
    }
}
