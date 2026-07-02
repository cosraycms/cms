<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Celemas\Core\App;
use Cosray\Plugin\Plugin;
use Cosray\Plugin\Registrar;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Field\TestMoney;
use Cosray\Tests\Fixtures\Node\PlainPage;
use stdClass;

final class TestPlugin implements Plugin
{
	public function id(): string
	{
		return 'test-plugin';
	}

	public function register(Registrar $cms): void
	{
		$cms->field(TestMoney::class, 'money');
		$cms->fieldSchema(TestBadge::class, new TestBadgeHandler());
		$cms->node(PlainPage::class);
		$cms->section('Test Plugin')->collection(TestArticlesCollection::class);
		$cms->migrations(__DIR__ . '/db/migrations');
		$cms->sql(__DIR__ . '/db/sql');
		$cms->register('test-plugin.service', stdClass::class);
		$cms->routes(static function (App $app): void {
			$app->get('/test-plugin', [stdClass::class, 'index'], 'test-plugin.route');
		});
	}
}
