<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Filter;

/**
 * The address of an order is a copy of the one the member picked in the address book of the company:
 * the two are the same place when street, postcode, city and country are equal (no function of one
 * database engine, and a missing postcode matches a missing postcode).
 */
final class AddressMatch
{
    private function __construct()
    {
    }

    public static function sql(string $book, string $delivery): string
    {
        return sprintf(
            '%1$s.street = %2$s.street AND %1$s.city = %2$s.city AND %1$s.country_code = %2$s.country_code AND (%1$s.postcode = %2$s.postcode OR (%1$s.postcode IS NULL AND %2$s.postcode IS NULL))',
            $book,
            $delivery,
        );
    }
}
