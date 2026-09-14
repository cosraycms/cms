<?php

declare(strict_types=1);

use Celema\Container\Container;
use Celema\Quma\Connection;
use Celema\Quma\Database;
use Celema\Quma\Delimiters;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Services;
use Cosray\Fulltext\Rebuild;
use Cosray\Locales;
use Cosray\Tests\Fixtures\Node\FulltextPage;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$config = new Config($root);
$db = new Database(new Connection(getenv('COSRAY_FTS_DSN'), $root . '/db/sql')->placeholders(
	Delimiters::comments(),
	$config->db->placeholders,
));
$db->execute(file_get_contents(__DIR__ . '/sql/fulltext/worker.sql'), ['name' => $argv[1]])->run();
$locales = new Locales();
$locales->add('en', 'English', pgDict: 'english');
$locales->add('de', 'German', fallback: 'en', pgDict: 'german');
$container = new Container();
$container->tag(Bootstrap::NODE_TAG)->add('fulltext-page', FulltextPage::class);
$report = new Rebuild($db, $locales, Services::withDefaults(), $container)->run();
echo json_encode($report, JSON_THROW_ON_ERROR);
exit($report['failed'] > 0 ? 1 : 0);
