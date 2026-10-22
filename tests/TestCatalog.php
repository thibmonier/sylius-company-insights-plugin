<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyAddressInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Factory\CompanyMemberFactoryInterface;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * Data created by the tests themselves, inside their rolled-back transaction, so that no test
 * depends on the random Sylius fixtures: companies, members, and orders written directly with the
 * amounts, the dates and the tax adjustments the test needs. The company link of an order is
 * recorded by the flush, as for any order completed by a signed-in member.
 */
final class TestCatalog
{
    public readonly string $suffix;

    private int $sequence = 0;

    private ?ProductVariantInterface $variant = null;

    public function __construct(private readonly ContainerInterface $container)
    {
        $this->suffix = strtolower(bin2hex(random_bytes(4)));
    }

    public function channel(): ChannelInterface
    {
        $repository = $this->entityManager()->getRepository(ChannelInterface::class);
        $channel = $repository->findOneBy(['hostname' => 'localhost']) ?? $repository->findOneBy(['enabled' => true]);
        Assert::isInstanceOf($channel, ChannelInterface::class);

        return $channel;
    }

    public function localeCode(): string
    {
        return (string) $this->channel()->getDefaultLocale()?->getCode();
    }

    /** A customer with an enabled, verified shop account (password `sylius`). */
    public function customer(string $name = 'buyer', string $firstName = 'Ada', string $lastName = 'Lovelace'): CustomerInterface
    {
        /** @var FactoryInterface<CustomerInterface> $customerFactory */
        $customerFactory = $this->container->get('sylius.factory.customer');
        $customer = $customerFactory->createNew();
        $customer->setEmail(sprintf('%s-%s@company-insights.test', $name, $this->suffix));
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        /** @var FactoryInterface<ShopUserInterface> $userFactory */
        $userFactory = $this->container->get('sylius.factory.shop_user');
        $user = $userFactory->createNew();
        // Random: the tests sign in with loginUser(), and no password literal is left for secret scanners.
        $user->setPlainPassword(bin2hex(random_bytes(16)));
        $user->setEnabled(true);
        $user->setVerifiedAt(new \DateTime());
        $customer->setUser($user);
        $this->entityManager()->persist($customer);
        $this->entityManager()->flush();

        return $customer;
    }

    public function company(string $name = 'Acme', bool $enabled = true): CompanyInterface
    {
        /** @var FactoryInterface<CompanyInterface> $factory */
        $factory = $this->container->get('tmonier_company_accounts.factory.company');
        $company = $factory->createNew();
        $company->setCode(strtoupper(sprintf('%s_%s_%d', $name, $this->suffix, ++$this->sequence)));
        $company->setName($name . ' ' . $this->suffix);
        $company->setVatNumber('FR40123456824');
        $company->setRegistrationNumber('123 456 824');
        $company->setEnabled($enabled);
        $this->entityManager()->persist($company);
        $this->entityManager()->flush();

        return $company;
    }

    public function member(CompanyInterface $company, ?CustomerInterface $customer = null, string $role = CompanyMemberInterface::ROLE_BUYER, bool $enabled = true): CompanyMemberInterface
    {
        /** @var CompanyMemberFactoryInterface $factory */
        $factory = $this->container->get('tmonier_company_accounts.factory.company_member');
        $member = $factory->createForCustomer($company, $customer ?? $this->customer($role . '-' . ++$this->sequence), $role);
        $member->setEnabled($enabled);
        $this->entityManager()->persist($member);
        $this->entityManager()->flush();

        return $member;
    }

    /**
     * A completed order of the customer. `$total` is the amount the customer pays, tax included;
     * `$tax` is recorded as an order-level tax adjustment and `$unitTax` as a tax adjustment of
     * the (only) unit of the order, `$shipmentTax` as one of its shipment. The tax-exclusive total
     * is `$total - $tax - $unitTax - $shipmentTax`.
     */
    public function order(CustomerInterface $customer, int $total, string $completedAt = 'now', string $currency = 'EUR', string $state = OrderInterface::STATE_NEW, int $tax = 0, int $unitTax = 0, int $shipmentTax = 0): OrderInterface
    {
        $order = $this->newOrder($customer, $completedAt, $currency, $state);
        $this->addItem($order, $this->variant(), 1);
        $this->entityManager()->persist($order);
        $this->entityManager()->flush();

        $this->connection()->executeStatement(
            'UPDATE sylius_order SET items_total = :total, adjustments_total = 0, total = :total WHERE id = :id',
            ['total' => $total, 'id' => $order->getId()],
        );
        if ($tax > 0) {
            $this->insertAdjustment('order_id', (int) $order->getId(), $tax);
        }
        if ($unitTax > 0) {
            $unit = $order->getItems()->first()->getUnits()->first();
            Assert::notFalse($unit);
            $this->insertAdjustment('order_item_unit_id', (int) $unit->getId(), $unitTax);
        }
        if ($shipmentTax > 0) {
            $this->insertAdjustment('shipment_id', $this->insertShipment((int) $order->getId()), $shipmentTax);
        }

        return $order;
    }

    /**
     * An order with one line per entry. The total of a line is what the customer pays for it after the
     * discounts of the line, tax included; its tax is recorded on the first unit of the line.
     *
     * @param list<array{0: ProductVariantInterface, 1: int, 2: int, 3?: int}> $lines variant, quantity, total, tax
     */
    public function orderOfLines(CustomerInterface $customer, array $lines, string $completedAt = 'now', string $currency = 'EUR', string $state = OrderInterface::STATE_NEW): OrderInterface
    {
        $order = $this->newOrder($customer, $completedAt, $currency, $state);
        foreach ($lines as [$variant, $quantity]) {
            $this->addItem($order, $variant, $quantity);
        }
        $this->entityManager()->persist($order);
        $this->entityManager()->flush();

        $total = 0;
        foreach (array_values($order->getItems()->toArray()) as $index => $item) {
            $lineTotal = $lines[$index][2];
            $total += $lineTotal;
            $this->connection()->executeStatement(
                'UPDATE sylius_order_item SET units_total = :total, adjustments_total = 0, total = :total WHERE id = :id',
                ['total' => $lineTotal, 'id' => $item->getId()],
            );
            $unit = $item->getUnits()->first();
            if (($lines[$index][3] ?? 0) > 0 && false !== $unit) {
                $this->insertAdjustment('order_item_unit_id', (int) $unit->getId(), $lines[$index][3]);
            }
        }
        $this->connection()->executeStatement(
            'UPDATE sylius_order SET items_total = :total, adjustments_total = 0, total = :total WHERE id = :id',
            ['total' => $total, 'id' => $order->getId()],
        );

        return $order;
    }

    public function taxon(string $code, string $name): TaxonInterface
    {
        $taxon = $this->container->get('sylius.factory.taxon')->createNew();
        $taxon->setCode($code . '_' . $this->suffix);
        $taxon->setCurrentLocale($this->localeCode());
        $taxon->setFallbackLocale($this->localeCode());
        $taxon->setName($name);
        $taxon->setSlug(strtolower($code) . '-' . $this->suffix);
        $this->entityManager()->persist($taxon);
        $this->entityManager()->flush();

        return $taxon;
    }

    /**
     * A product with one variant, named in the given locales only (locale code => name).
     *
     * @param array<string, string> $names
     */
    public function product(string $code, array $names, ?TaxonInterface $mainTaxon = null, ?string $variantName = null): ProductVariantInterface
    {
        $channel = $this->channel();
        $product = $this->container->get('sylius.factory.product')->createWithVariant();
        $product->setCode($code . '_' . $this->suffix);
        foreach ($names as $locale => $name) {
            $product->setCurrentLocale($locale);
            $product->setName($name);
            $product->setSlug(strtolower($code) . '-' . $locale . '-' . $this->suffix);
        }
        $product->setEnabled(true);
        $product->addChannel($channel);
        $product->setMainTaxon($mainTaxon);

        $variant = $product->getVariants()->first();
        Assert::isInstanceOf($variant, ProductVariantInterface::class);
        $variant->setCode($code . '-' . $this->suffix);
        if (null !== $variantName) {
            foreach (array_keys($names) as $locale) {
                $variant->setCurrentLocale($locale);
                $variant->setName($variantName);
            }
        }
        $variant->setEnabled(true);
        $variant->setTracked(false);
        $pricing = $this->container->get('sylius.factory.channel_pricing')->createNew();
        $pricing->setChannelCode($channel->getCode());
        $pricing->setPrice(2500);
        $pricing->setOriginalPrice(2500);
        $variant->addChannelPricing($pricing);

        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        return $variant;
    }

    public function pendingApproval(OrderInterface $order, string $state = 'pending', string $createdAt = 'now', ?string $decidedAt = null): void
    {
        $this->connection()->insert('tmonier_purchase_approval_order_approval', [
            'order_id' => $order->getId(),
            'company_id' => $this->connection()->fetchOne('SELECT company_id FROM tmonier_company_order WHERE order_id = ?', [$order->getId()]) ?: null,
            'state' => $state,
            'total' => (int) $this->connection()->fetchOne('SELECT total FROM sylius_order WHERE id = ?', [$order->getId()]),
            'currency_code' => $order->getCurrencyCode(),
            'created_at' => (new \DateTimeImmutable($createdAt))->format('Y-m-d H:i:s'),
            'decided_at' => null === $decidedAt ? null : (new \DateTimeImmutable($decidedAt))->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * The credit line of the company (the account of the trade credit plugin is owned by the company), written
     * directly: the plugin offers no setter for the amount in use.
     */
    /**
     * Copies a completed order of a company $count times, each copy with its own approval, by SQL: a volume
     * that creating the orders one by one through Doctrine would make too slow.
     */
    public function cloneApprovals(OrderInterface $seed, int $count, string $state, string $createdAt, ?string $decidedAt = null): void
    {
        $connection = $this->connection();
        $columns = array_values(array_filter(
            array_map(static fn (\Doctrine\DBAL\Schema\Column $column): string => $column->getName(), $connection->createSchemaManager()->listTableColumns('sylius_order')),
            static fn (string $name): bool => 'id' !== $name,
        ));
        $companyId = $connection->fetchOne('SELECT company_id FROM tmonier_company_order WHERE order_id = ?', [$seed->getId()]);
        $total = (int) $connection->fetchOne('SELECT total FROM sylius_order WHERE id = ?', [$seed->getId()]);
        $created = (new \DateTimeImmutable($createdAt))->format('Y-m-d H:i:s');
        $decided = null === $decidedAt ? null : (new \DateTimeImmutable($decidedAt))->format('Y-m-d H:i:s');
        $connection->beginTransaction();
        for ($index = 0; $index < $count; ++$index) {
            $id = $this->explicitId('sylius_order');
            // A quoted literal, not a parameter: PostgreSQL cannot infer the type of a bound value inside CONCAT().
            $suffix = $connection->quote(sprintf('-c%d', ++$this->sequence));
            $select = implode(', ', array_map(static fn (string $name): string => match ($name) {
                'number', 'token_value' => sprintf('CONCAT(%s, %s)', $name, $suffix),
                default => $name,
            }, $columns));
            $connection->executeStatement(
                sprintf('INSERT INTO sylius_order (%s) SELECT %s FROM sylius_order WHERE id = :seed', implode(', ', [...array_keys($id), ...$columns]), implode(', ', [...array_map(static fn (int $value): string => (string) $value, $id), ...[$select]])),
                ['seed' => $seed->getId()],
            );
            $orderId = (int) ($id['id'] ?? $connection->lastInsertId());
            $connection->insert('tmonier_company_order', [...$this->explicitId('tmonier_company_order'), 'company_id' => $companyId, 'order_id' => $orderId, 'created_at' => $created]);
            $connection->insert('tmonier_purchase_approval_order_approval', [
                'order_id' => $orderId, 'company_id' => $companyId, 'state' => $state, 'total' => $total,
                'currency_code' => $seed->getCurrencyCode(), 'created_at' => $created, 'decided_at' => $decided,
            ]);
        }
        $connection->commit();
    }

    public function tradeCreditAccount(CompanyInterface $company, int $limit, int $committed, string $currency = 'EUR', bool $hold = false): int
    {
        $this->connection()->insert('tmonier_trade_credit_account', [
            ...$this->explicitId('tmonier_trade_credit_account'),
            'owner_type' => 'company',
            'owner_identifier' => (string) $company->getId(),
            'currency_code' => $currency,
            'credit_limit' => $limit,
            'committed_amount' => $committed,
            'allow_exceed' => 0,
            'credit_hold' => $hold ? 1 : 0,
            'block_when_overdue' => 0,
            'enabled' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection()->fetchOne('SELECT id FROM tmonier_trade_credit_account WHERE owner_type = ? AND owner_identifier = ?', ['company', (string) $company->getId()]);
    }

    /** An invoice on account still to be paid by the company, due on the given date. */
    public function openInvoice(int $accountId, OrderInterface $order, int $amount, string $dueDate): void
    {
        $this->connection()->insert('tmonier_trade_credit_order_terms', [
            'id' => Uuid::v7()->toRfc4122(),
            'order_id' => $order->getId(),
            'account_id' => $accountId,
            'amount' => $amount,
            'currency_code' => $order->getCurrencyCode(),
            'net_days' => 30,
            'end_of_month_mode' => 'none',
            'due_date' => (new \DateTimeImmutable($dueDate))->format('Y-m-d'),
            'state' => 'reserved',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /** The id of a persisted entity, as an integer. */
    public function id(object $entity): int
    {
        Assert::methodExists($entity, 'getId');
        $id = $entity->getId();
        Assert::integer($id);

        return $id;
    }

    /** The order is delivered to this address (a copy, as the checkout makes it). */
    public function deliverTo(OrderInterface $order, string $street, string $city, string $postcode = '69007', string $country = 'FR'): void
    {
        $order->setShippingAddress($this->address($street, $city, $postcode, $country));
        $this->entityManager()->flush();
    }

    /** An entry of the address book of the company: a label and an address. */
    public function addressBookEntry(CompanyInterface $company, string $label, string $street, string $city, string $postcode = '69007', string $country = 'FR'): CompanyAddressInterface
    {
        $class = $this->container->getParameter('tmonier_company_accounts.model.company_address.class');
        Assert::string($class);
        $entry = new $class();
        Assert::isInstanceOf($entry, CompanyAddressInterface::class);
        $entry->setCompany($company);
        $entry->setLabel($label);
        $entry->setAddress($this->address($street, $city, $postcode, $country));
        $this->entityManager()->persist($entry);
        $this->entityManager()->flush();

        return $entry;
    }

    public function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function address(string $street, string $city, string $postcode, string $country): AddressInterface
    {
        /** @var FactoryInterface<AddressInterface> $factory */
        $factory = $this->container->get('sylius.factory.address');
        $address = $factory->createNew();
        $address->setFirstName('Test');
        $address->setLastName('Address');
        $address->setStreet($street);
        $address->setCity($city);
        $address->setPostcode($postcode);
        $address->setCountryCode($country);
        $this->entityManager()->persist($address);

        return $address;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    private function newOrder(CustomerInterface $customer, string $completedAt, string $currency, string $state): OrderInterface
    {
        /** @var FactoryInterface<OrderInterface> $orderFactory */
        $orderFactory = $this->container->get('sylius.factory.order');
        $order = $orderFactory->createNew();
        $order->setChannel($this->channel());
        $order->setLocaleCode($this->localeCode());
        $order->setCurrencyCode($currency);
        $order->setCustomerWithAuthorization($customer);
        $order->setTokenValue(bin2hex(random_bytes(10)));
        $order->setNumber(sprintf('INS-%s-%03d', $this->suffix, ++$this->sequence));
        $order->setState($state);
        $order->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
        $order->setPaymentState('awaiting_payment');
        $order->setShippingState('ready');
        $order->setCheckoutCompletedAt(new \DateTime($completedAt));

        return $order;
    }

    private function addItem(OrderInterface $order, ProductVariantInterface $variant, int $quantity): void
    {
        /** @var FactoryInterface<OrderItemInterface> $itemFactory */
        $itemFactory = $this->container->get('sylius.factory.order_item');
        $item = $itemFactory->createNew();
        $item->setVariant($variant);
        /** @var OrderItemQuantityModifierInterface $quantityModifier */
        $quantityModifier = $this->container->get('sylius.modifier.order_item_quantity');
        $quantityModifier->modify($item, $quantity);
        /** @var OrderModifierInterface $orderModifier */
        $orderModifier = $this->container->get('sylius.modifier.order');
        $orderModifier->addToOrder($order, $item);
    }

    private function insertAdjustment(string $column, int $id, int $amount): void
    {
        $this->connection()->insert('sylius_adjustment', [
            ...$this->explicitId('sylius_adjustment'),
            $column => $id,
            'type' => AdjustmentInterface::TAX_ADJUSTMENT,
            'label' => 'VAT',
            'details' => '[]',
            'amount' => $amount,
            'is_neutral' => 1,
            'is_locked' => 0,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * MySQL fills the id itself (auto-increment). PostgreSQL does not: Doctrine maps the ids to a sequence that is not
     * the column default, so a raw insert must take the next value of that sequence.
     *
     * @return array{id?: int}
     */
    private function explicitId(string $table): array
    {
        if (!$this->connection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return [];
        }

        return ['id' => (int) $this->connection()->fetchOne(\sprintf("SELECT nextval('%s_id_seq')", $table))];
    }

    private function insertShipment(int $orderId): int
    {
        $method = $this->connection()->fetchOne('SELECT id FROM sylius_shipping_method LIMIT 1');
        Assert::notFalse($method, 'The test database needs the Sylius fixtures (a shipping method).');
        $id = $this->explicitId('sylius_shipment');
        $this->connection()->insert('sylius_shipment', [
            ...$id,
            'method_id' => $method,
            'order_id' => $orderId,
            'state' => 'ready',
            'adjustments_total' => 0,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (int) ($id['id'] ?? $this->connection()->lastInsertId());
    }

    private function variant(): ProductVariantInterface
    {
        if ($this->variant instanceof ProductVariantInterface) {
            return $this->variant;
        }

        $channel = $this->channel();
        /** @var ProductFactoryInterface<ProductInterface> $productFactory */
        $productFactory = $this->container->get('sylius.factory.product');
        /** @var FactoryInterface<ChannelPricingInterface> $pricingFactory */
        $pricingFactory = $this->container->get('sylius.factory.channel_pricing');
        $code = 'tmonier_insights_' . $this->suffix;

        $product = $productFactory->createWithVariant();
        $product->setCode($code);
        $product->setCurrentLocale($this->localeCode());
        $product->setFallbackLocale($this->localeCode());
        $product->setName('Office chair ' . $this->suffix);
        $product->setSlug($code);
        $product->setEnabled(true);
        $product->addChannel($channel);

        $variant = $product->getVariants()->first();
        Assert::isInstanceOf($variant, ProductVariantInterface::class);
        $variant->setCode($code . '_variant');
        $variant->setCurrentLocale($this->localeCode());
        $variant->setFallbackLocale($this->localeCode());
        $variant->setName('Black');
        $variant->setEnabled(true);
        $variant->setTracked(false);
        $pricing = $pricingFactory->createNew();
        $pricing->setChannelCode($channel->getCode());
        $pricing->setPrice(2500);
        $pricing->setOriginalPrice(2500);
        $variant->addChannelPricing($pricing);

        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        return $this->variant = $variant;
    }
}
