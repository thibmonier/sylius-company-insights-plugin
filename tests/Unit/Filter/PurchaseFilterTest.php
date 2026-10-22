<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;

final class PurchaseFilterTest extends TestCase
{
    public function testAnEmptyFilterAddsNothing(): void
    {
        $filter = new PurchaseFilter();

        self::assertTrue($filter->isEmpty());
        self::assertSame('', $filter->sql());
        self::assertSame([], $filter->parameters());
    }

    public function testTheMemberReachesTheQueryAsAParameter(): void
    {
        $filter = new PurchaseFilter(customerId: 42);

        self::assertFalse($filter->isEmpty());
        self::assertSame(' AND o.customer_id = :filter_customer', $filter->sql());
        self::assertSame(['filter_customer' => 42], $filter->parameters());
    }

    public function testTheSiteIsAnAddressOfTheBookOfTheCompanyOfTheOrder(): void
    {
        $filter = new PurchaseFilter(addressId: 5);

        self::assertStringContainsString('filter_book.company_id = co.company_id', $filter->sql());
        self::assertStringContainsString(':filter_site', $filter->sql());
        self::assertStringNotContainsString('5', $filter->sql());
        self::assertSame(['filter_site' => 5], $filter->parameters());
    }
}
