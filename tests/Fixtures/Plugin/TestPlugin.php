<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Celema\Core\App;
use Cosray\Plugin\PanelRoutes;
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
		$cms->icons(__DIR__ . '/icons');
		$cms->blockType(TestNotice::class);
		$cms->blockType(TestCallout::class);
		$cms->control('test-money-picker', 'test-money-picker', 'controls.js');
		$cms->dashboardCard(TestDashboardCard::class);
		$cms->nodeListener(TestWriteLog::class);
		$cms->templates(__DIR__ . '/views');
		$cms->translations(__DIR__ . '/lang', scripts: true);
		$cms->panel(static function (PanelRoutes $panel): void {
			$panel->get('', [PageController::class, 'index'], 'index')->render('test-plugin:page');
			$panel->post('', [PageController::class, 'save'], 'save');
			$panel->get('/stats', [SystemPageController::class, 'index'], 'stats')->render('test-plugin:page');
			$panel->get('/stats/today', [SystemPageController::class, 'index'], 'stats.today')->render(
				'test-plugin:page',
			);
			$panel->get('/sub', [PageController::class, 'index'], 'sub')->render('test-plugin:page');
		});
		$cms->css('theme.css');
		$cms->css("{$cms->config->panel->path}/vendor/test-plugin/theme.css");
		$cms->section('Test Plugin')->link('Test Page', $cms->panelUrl());
		$cms->section('Test Plugin')->link('Other Page', $cms->panelUrl('/sub'));
		$cms->allow('role:admin', 'test-plugin.view-stats');
		$cms
			->system()
			->link('Test Stats', $cms->panelUrl('/stats'))
			->permission('test-plugin.view-stats');
		$cms->register('test-plugin.service', stdClass::class);
		$cms->routes(static function (App $app): void {
			$app->get('/test-plugin', [stdClass::class, 'index'], 'test-plugin.route');
		});
	}
}
