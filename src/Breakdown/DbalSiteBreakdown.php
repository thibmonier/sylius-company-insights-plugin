<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

use Tmonier\SyliusCompanyInsightsPlugin\Filter\AddressMatch;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingQueryTrait;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingScope;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

final readonly class DbalSiteBreakdown implements SiteBreakdownInterface
{
    use RankingQueryTrait;

    public function rows(RankingScope $scope): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT (SELECT MIN(book.label) FROM tmonier_company_address book INNER JOIN sylius_address book_address ON book_address.id = book.address_id WHERE book.company_id = :company AND %s) AS label, COUNT(o.id) AS orders_count, SUM(%s) AS spend FROM %s LEFT JOIN sylius_address delivery ON delivery.id = o.shipping_address_id WHERE %s GROUP BY delivery.street, delivery.postcode, delivery.city, delivery.country_code',
            AddressMatch::sql('book_address', 'delivery'),
            $this->sql->netTotal(),
            CountedOrderSql::FROM,
            $this->where($scope),
        ), $this->parameters($scope));

        return array_map(static function (array $row): BreakdownRow {
            Assert::nullOrString($row['label']);
            Assert::numeric($row['orders_count']);
            Assert::numeric($row['spend']);

            return new BreakdownRow($row['label'], (int) $row['orders_count'], (int) $row['spend']);
        }, $rows);
    }
}
