<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Filter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\MemberChoice;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\SelectionFactory;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\SiteChoice;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodResolver;

final class SelectionFactoryTest extends TestCase
{
    public function testWithoutAnyParameterItIsTheDefaultPeriodWithoutFilter(): void
    {
        $selection = $this->factory()->create(7, []);

        self::assertSame(PeriodKey::Last12Months, $selection->periods->key);
        self::assertTrue($selection->filter->isEmpty());
        self::assertSame(['period' => 'last_12_months'], $selection->query);
    }

    public function testTheChoicesAreThoseOfTheCompanyOfTheMember(): void
    {
        $choices = new FakeFilterChoices();

        (new SelectionFactory($this->resolver(), $choices))->create(7, ['member' => '12']);

        self::assertSame(7, $choices->askedFor);
    }

    public function testAMemberAndASiteOfTheCompanyAreKept(): void
    {
        $selection = $this->factory()->create(7, ['member' => '12', 'site' => '3', 'period' => 'last_30_days']);

        self::assertSame(12, $selection->filter->customerId);
        self::assertSame(3, $selection->filter->addressId);
        self::assertSame(['period' => 'last_30_days', 'member' => '12', 'site' => '3'], $selection->query);
    }

    #[DataProvider('foreignOrMalformed')]
    public function testAMemberOrASiteOutsideTheCompanyOrMalformedIsIgnored(mixed $member, mixed $site): void
    {
        $selection = $this->factory()->create(7, ['member' => $member, 'site' => $site]);

        self::assertTrue($selection->filter->isEmpty());
        self::assertSame(['period' => 'last_12_months'], $selection->query);
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function foreignOrMalformed(): iterable
    {
        yield 'member of another company' => ['999', '999'];
        yield 'injection attempt' => ['12 OR 1=1', '3; DROP TABLE sylius_order'];
        yield 'array' => [['12'], ['3']];
        yield 'negative' => ['-12', '-3'];
        yield 'empty' => ['', null];
        yield 'huge' => [str_repeat('9', 40), str_repeat('9', 40)];
    }

    public function testACustomPeriodIsTheWholeDaysBetweenTwoDatesWithThePeriodJustBefore(): void
    {
        $selection = $this->factory()->create(7, ['from' => '2026-03-01', 'to' => '2026-03-31']);

        self::assertSame(PeriodKey::Custom, $selection->periods->key);
        self::assertSame('2026-03-01', $selection->periods->current->from->format('Y-m-d'));
        self::assertSame('2026-04-01', $selection->periods->current->to->format('Y-m-d'));
        self::assertSame('2026-01-29', $selection->periods->previous->from->format('Y-m-d'));
        self::assertSame('2026-03-01', $selection->periods->previous->to->format('Y-m-d'));
        self::assertSame(['period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31'], $selection->query);
    }

    #[DataProvider('badCustomPeriods')]
    public function testAnInvalidCustomPeriodFallsBackToTheDefaultPeriod(mixed $from, mixed $to): void
    {
        $selection = $this->factory()->create(7, ['period' => 'custom', 'from' => $from, 'to' => $to]);

        self::assertSame(PeriodKey::Last12Months, $selection->periods->key);
        self::assertSame(['period' => 'last_12_months'], $selection->query);
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function badCustomPeriods(): iterable
    {
        yield 'end before start' => ['2026-03-31', '2026-03-01'];
        yield 'not a date' => ['yesterday', '2026-03-01'];
        yield 'impossible day' => ['2026-02-30', '2026-03-01'];
        yield 'missing end' => ['2026-03-01', null];
        yield 'before 2000' => ['1999-01-01', '2026-03-01'];
        yield 'custom without dates' => [null, null];
    }

    public function testTwoValidDatesWinOverThePresetPeriod(): void
    {
        $selection = $this->factory()->create(7, ['period' => 'last_30_days', 'from' => '2026-03-01', 'to' => '2026-03-31']);

        self::assertSame(PeriodKey::Custom, $selection->periods->key);
    }

    public function testEmptyDatesOfTheFormKeepThePresetPeriod(): void
    {
        $selection = $this->factory()->create(7, ['period' => 'last_30_days', 'from' => '', 'to' => '']);

        self::assertSame(PeriodKey::Last30Days, $selection->periods->key);
        self::assertSame(['period' => 'last_30_days'], $selection->query);
    }

    private function factory(): SelectionFactory
    {
        return new SelectionFactory($this->resolver(), new FakeFilterChoices([new MemberChoice(12, 'Claire Martin')], [new SiteChoice(3, 'Lyon plant')]));
    }

    private function resolver(): PeriodResolver
    {
        return new PeriodResolver(new MockClock('2026-10-21 15:30:00'), 'Europe/Paris');
    }
}
