<?php

use function Cosray\escape;

$this->layout('layer/main');

$system = (array) $this->unwrap($system);
$panelBase = (string) $panelBase;
$locales = (array) ($system['locales'] ?? []);
$defaultLocale = (string) ($system['defaultLocale'] ?? '');
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>

<div
	class="page cms-media"
	data-content-locale-scope
	data-content-locale="<?= escape($defaultLocale) ?>">
	<header class="head">
		<h1><?= escape(__('media:title')) ?></h1>
		<?php $this->insert('component/content-locales', [
			'locales' => $locales,
			'selected' => $defaultLocale,
			'controlId' => 'cms-media-locale',
			'labelled' => false,
		]) ?>
		<div class="actions" data-media-toolbar></div>
	</header>

	<section class="body">
		<cosray-media-library data-cosray-element="media-library"></cosray-media-library>
	</section>

	<script id="cosray-system-data" type="application/json"><?= json_encode(
		['panel' => $panelBase, 'system' => $system],
		$jsonFlags,
	) ?></script>
</div>
