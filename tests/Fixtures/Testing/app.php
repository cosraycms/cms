<?php

declare(strict_types=1);

use Cosray\App;
use Cosray\Locales;
use Cosray\Tests\Fixtures\Node\TestPage;
use Cosray\Tests\Fixtures\Testing\Signups;
use Psr\Log\NullLogger;

/** A site's app, as its boot file would create it. */
return function (string $dsn): App {
	$root = dirname(__DIR__, 3);
	$app = App::create($root, [
		'app.name' => 'cosray',
		'db.dsn' => $dsn,
		'path.public' => "{$root}/public",
		'path.views' => '/tests/Fixtures/templates',
		'mail.transports' => ['default' => 'null://null'],
		'mail.from' => 'Site <site@example.com>',
	]);

	$locales = new Locales();
	$locales->add('en', title: 'English', pgDict: 'english');
	$app->load($locales);
	$app->logger(new NullLogger());
	$app->node(TestPage::class);
	$app->post('/signups', [Signups::class, 'store']);
	$app->post('/signups/abandon', [Signups::class, 'abandon']);
	$app->post('/signups/break', [Signups::class, 'break']);
	$app->boot();

	return $app;
};
