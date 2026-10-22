<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Filter;

/**
 * What narrows the orders of the company: one member (by customer) and/or one delivery site (an address
 * of the address book of the company). Both come from allow-lists built from the company of the
 * signed-in member (see `SelectionFactory`), and reach the SQL as parameters only.
 */
final readonly class PurchaseFilter
{
    public function __construct(
        public ?int $customerId = null,
        public ?int $addressId = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->customerId && null === $this->addressId;
    }

    /** Conditions on the aliases `o` (order) and `co` (company order), to append to a WHERE clause. */
    public function sql(): string
    {
        $sql = '';
        if (null !== $this->customerId) {
            $sql .= ' AND o.customer_id = :filter_customer';
        }
        if (null !== $this->addressId) {
            $sql .= ' AND EXISTS (SELECT 1 FROM tmonier_company_address filter_book INNER JOIN sylius_address filter_site ON filter_site.id = filter_book.address_id INNER JOIN sylius_address filter_delivery ON filter_delivery.id = o.shipping_address_id WHERE filter_book.id = :filter_site AND filter_book.company_id = co.company_id AND ' . AddressMatch::sql('filter_site', 'filter_delivery') . ')';
        }

        return $sql;
    }

    /** @return array<string, int> */
    public function parameters(): array
    {
        $parameters = [];
        if (null !== $this->customerId) {
            $parameters['filter_customer'] = $this->customerId;
        }
        if (null !== $this->addressId) {
            $parameters['filter_site'] = $this->addressId;
        }

        return $parameters;
    }
}
