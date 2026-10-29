<?php

declare(strict_types=1);

use Celema\Verba\Translator;
use Cosray\Locales;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$locale = $argv[1] ?? 'en';

echo json_encode(new Translator($locale, new Locales()->catalogs())->exportMany(['panel']), JSON_THROW_ON_ERROR);
