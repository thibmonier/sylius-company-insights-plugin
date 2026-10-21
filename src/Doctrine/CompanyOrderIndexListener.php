<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyOrderInterface;

/**
 * Declares in the mapping of the company orders the index the aggregates rely on, so that the
 * schema tools of the application keep it (a plain migration would be dropped by the next diff).
 * `tmonier_company_order` belongs to company-accounts, which is left untouched.
 */
final class CompanyOrderIndexListener
{
    public const INDEX_NAME = 'tmonier_company_insights_company_order_idx';

    public const COLUMNS = ['company_id', 'order_id'];

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        if ($metadata->isMappedSuperclass || !is_a($metadata->getName(), CompanyOrderInterface::class, true)) {
            return;
        }

        $metadata->table['indexes'][self::INDEX_NAME] = ['columns' => self::COLUMNS];
    }
}
