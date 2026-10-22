<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingQueryTrait;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\RankingScope;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

final readonly class DbalOrderSpend implements OrderSpendInterface
{
    use RankingQueryTrait;

    public function perOrder(RankingScope $scope): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT o.id AS id, %s AS spend FROM %s WHERE %s ORDER BY o.id',
            $this->sql->netTotal(),
            CountedOrderSql::FROM,
            $this->where($scope),
        ), $this->parameters($scope));

        $spend = [];
        foreach ($rows as $row) {
            Assert::numeric($row['id']);
            Assert::numeric($row['spend']);
            $spend[(int) $row['id']] = (int) $row['spend'];
        }

        return $spend;
    }
}
