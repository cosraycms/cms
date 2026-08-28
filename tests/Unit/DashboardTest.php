<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\App;
use Cosray\Contract\DashboardCard;
use Cosray\Exception\RuntimeException;
use Cosray\Panel\Dashboard;
use Cosray\Panel\Dashboard\Card;
use Cosray\Panel\Dashboard\Drafts;
use Cosray\Panel\Dashboard\Entries;
use Cosray\Panel\Dashboard\Media;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * @covers \Cosray\Panel\Dashboard
 */
final class DashboardTest extends TestCase
{
	public function testDefaultCardsHaveAStableOrder(): void
	{
		$this->assertSame(
			[Entries::class, Drafts::class, Media::class],
			Dashboard::withDefaults()->cards(),
		);
	}

	public function testAppExposesItsDashboardAsAProperty(): void
	{
		$app = App::create(dirname(__DIR__, 2));
		$app->dashboard->replace(TestDashboardCard::class);

		$this->assertSame([TestDashboardCard::class], $app->dashboard->cards());
	}

	public function testCardsCanBeAppendedOrReplacedAsAnOrderedList(): void
	{
		$instance = new TestDashboardCard();
		$dashboard = Dashboard::withDefaults()->add($instance);
		$this->assertSame($instance, $dashboard->cards()[3]);

		$dashboard->replace(TestDashboardCard::class, $instance);
		$this->assertSame([TestDashboardCard::class, $instance], $dashboard->cards());
	}

	public function testCardClassesMustImplementTheContract(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(DashboardCard::class);

		new Dashboard([stdClass::class]);
	}

	public function testCardClassesMustBeInstantiable(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('must be instantiable');

		new Dashboard([AbstractDashboardCard::class]);
	}
}

abstract class AbstractDashboardCard implements DashboardCard {}

final class TestDashboardCard implements DashboardCard
{
	public function card(): Card
	{
		return new Card('Test', 1);
	}
}
