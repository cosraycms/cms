<?php

declare(strict_types=1);

use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Locales;
use Cosray\View\Boiler\Renderer;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$context = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
Verba::activate(new Translator('en', new Locales()->catalogs()));

echo new Renderer(dirname(__DIR__, 3) . '/panel/views')->render('field/field', $context);
