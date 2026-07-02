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
		$cms->assets(__DIR__ . '/assets');
		$cms->blockType(TestNotice::class);
		$cms->control('test-money-picker', 'test-money-picker', 'controls.js');
		$cms->templates(__DIR__ . '/views');
		$cms->panelPage('/test-plugin', [PageController::class, 'index'], 'test-plugin:page', 'index');
		$cms->css("{$cms->config->panel->path}/vendor/test-plugin/theme.css");
		$cms
			->section('Test Plugin')
			->link('Test Page', "{$cms->config->panel->path}/test-plugin");
		$cms->register('test-plugin.service', stdClass::class);
		$cms->routes(static function (App $app): void {
			$app->get('/test-plugin', [stdClass::class, 'index'], 'test-plugin.route');
		});
	}
}
