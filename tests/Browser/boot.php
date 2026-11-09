<?php

declare(strict_types=1);

use Cosray\App;
use Cosray\Locales;
use Cosray\Tests\Browser\Node\Article;
use Cosray\Tests\Browser\Node\MediaPage;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$setting = static fn(string $key, string $default): string => getenv($key) ?: $default;
$root = __DIR__;
$app = App::create($root, [
	'db.dsn' => sprintf(
		'pgsql:host=%s;dbname=%s;user=%s;password=%s',
		$setting('COSRAY_DB_HOST', 'localhost'),
		$setting('COSRAY_BROWSER_DB', 'cosray_browser'),
		$setting('COSRAY_DB_USER', 'cosray'),
		$setting('COSRAY_DB_PASSWORD', 'cosray'),
	),
	'app.secret' => 'browser-tests',
	'path.public' => "{$root}/.run/public",
	'path.views' => '/views',
	'media.sizes' => ['wide' => ['width' => 600]],
]);
$locales = new Locales();
$locales->add('en', title: 'English', pgDict: 'english');
$app->node(MediaPage::class);
$app->node(Article::class);
$app->load($locales);
$app->boot();

return $app;
