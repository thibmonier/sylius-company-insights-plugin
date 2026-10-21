<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Tmonier\SyliusCompanyInsightsPlugin\InsightsTestTrait;

trait FunctionalTestTrait
{
    use InsightsTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    private function loginShopUser(CustomerInterface $customer): void
    {
        $user = $customer->getUser();
        self::assertInstanceOf(ShopUserInterface::class, $user);
        $this->client->loginUser($user, 'shop');
    }

    private function shopUrl(string $path, ?string $locale = null): string
    {
        return sprintf('/%s%s', $locale ?? $this->catalog()->localeCode(), $path);
    }

    /** Makes a locale available in the channel (created if needed). */
    private function enableLocale(string $code): void
    {
        $entityManager = $this->entityManager();
        $locale = $entityManager->getRepository(LocaleInterface::class)->findOneBy(['code' => $code]);
        if (null === $locale) {
            /** @var FactoryInterface<LocaleInterface> $factory */
            $factory = self::getContainer()->get('sylius.factory.locale');
            $locale = $factory->createNew();
            $locale->setCode($code);
            $entityManager->persist($locale);
        }
        self::assertInstanceOf(LocaleInterface::class, $locale);
        $this->catalog()->channel()->addLocale($locale);
        $entityManager->flush();
    }
}
