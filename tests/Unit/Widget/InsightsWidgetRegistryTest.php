<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Widget;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodComparison;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidget;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidgetProviderInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\InsightsWidgetRegistry;
use Tmonier\SyliusCompanyInsightsPlugin\Widget\WidgetContext;

final class InsightsWidgetRegistryTest extends TestCase
{
    public function testWithoutAnyProviderThereIsNoWidget(): void
    {
        self::assertSame([], $this->registry([])->widgets($this->context()));
    }

    public function testTheWidgetsOfTheProvidersAreReturnedInOrder(): void
    {
        $widgets = $this->registry([$this->provider('approvals'), $this->provider('credit')])->widgets($this->context());

        self::assertSame(['approvals', 'credit'], array_map(static fn (InsightsWidget $widget): string => $widget->code, $widgets));
    }

    public function testAProviderThatHasNothingToShowIsLeftOut(): void
    {
        $widgets = $this->registry([$this->provider('approvals', widget: false), $this->provider('credit')])->widgets($this->context());

        self::assertSame(['credit'], array_map(static fn (InsightsWidget $widget): string => $widget->code, $widgets));
    }

    public function testAWidgetWhosePermissionIsNotGrantedIsNeitherComputedNorShown(): void
    {
        $denied = $this->provider('approvals', permission: 'APPROVAL_LIST');
        $granted = $this->provider('credit', permission: 'CREDIT_VIEW');

        $widgets = $this->registry([$denied, $granted], granted: ['CREDIT_VIEW'])->widgets($this->context());

        self::assertSame(['credit'], array_map(static fn (InsightsWidget $widget): string => $widget->code, $widgets));
        self::assertSame(0, $denied->calls, 'A widget the member may not see is not even computed.');
    }

    public function testTheCompanyIsTheSubjectOfThePermission(): void
    {
        $checked = [];
        $company = $this->createStub(CompanyInterface::class);
        $checker = new class($checked) implements AuthorizationCheckerInterface {
            /** @param list<mixed> $checked */
            public function __construct(public array &$checked)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, mixed $vote = null): bool
            {
                $this->checked[] = [$attribute, $subject];

                return true;
            }
        };

        (new InsightsWidgetRegistry([$this->provider('approvals', permission: 'APPROVAL_LIST')], $checker))->widgets($this->context($company));

        self::assertSame([['APPROVAL_LIST', $company]], $checked);
    }

    public function testAFailingProviderGivesAnUnavailableWidgetAndTheOthersStay(): void
    {
        $logger = new CollectingLogger();
        $failing = $this->provider('approvals', failure: new \RuntimeException('Claire Martin <claire@acme.example> owes 1200 EUR'));

        $widgets = $this->registry([$failing, $this->provider('credit')], logger: $logger)->widgets($this->context());

        self::assertCount(2, $widgets);
        self::assertTrue($widgets[0]->unavailable);
        self::assertSame('approvals', $widgets[0]->code);
        self::assertSame([], $widgets[0]->data);
        self::assertFalse($widgets[1]->unavailable);
    }

    public function testTheFailureIsLoggedWithoutAnyPersonalDataOrTrace(): void
    {
        $logger = new CollectingLogger();
        $failing = $this->provider('approvals', failure: new \RuntimeException('Claire Martin <claire@acme.example> owes 1200 EUR'));

        $this->registry([$failing], logger: $logger)->widgets($this->context());

        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame(['widget' => 'approvals', 'exception_class' => \RuntimeException::class], $logger->records[0]['context']);
        self::assertStringNotContainsString('Claire', json_encode($logger->records, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('claire@acme.example', json_encode($logger->records, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<StubWidgetProvider> $providers
     * @param list<string> $granted
     */
    private function registry(array $providers, array $granted = [], ?CollectingLogger $logger = null): InsightsWidgetRegistry
    {
        $checker = new class($granted) implements AuthorizationCheckerInterface {
            /** @param list<string> $granted */
            public function __construct(private readonly array $granted)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, mixed $vote = null): bool
            {
                return in_array($attribute, $this->granted, true);
            }
        };

        return new InsightsWidgetRegistry($providers, $checker, $logger);
    }

    private function provider(string $code, ?string $permission = null, bool $widget = true, ?\Throwable $failure = null): StubWidgetProvider
    {
        return new StubWidgetProvider($code, $permission, $widget, $failure);
    }

    private function context(?CompanyInterface $company = null): WidgetContext
    {
        $period = new Period(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-02-01'));

        return new WidgetContext(
            $company ?? $this->createStub(CompanyInterface::class),
            $this->createStub(CompanyMemberInterface::class),
            new PeriodComparison(PeriodKey::Last30Days, $period, $period),
            new PurchaseFilter(),
            'en_US',
        );
    }
}

final class StubWidgetProvider implements InsightsWidgetProviderInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly string $code,
        private readonly ?string $permission,
        private readonly bool $widget,
        private readonly ?\Throwable $failure,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function title(): string
    {
        return 'title.' . $this->code;
    }

    public function permission(): ?string
    {
        return $this->permission;
    }

    public function provide(WidgetContext $context): ?InsightsWidget
    {
        ++$this->calls;
        if ($this->failure instanceof \Throwable) {
            throw $this->failure;
        }

        return $this->widget ? new InsightsWidget($this->code, 'title.' . $this->code, 'widget.' . $this->code . '.html.twig', ['value' => 1]) : null;
    }
}

final class CollectingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => is_scalar($level) ? (string) $level : '', 'message' => (string) $message, 'context' => $context];
    }
}
