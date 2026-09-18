<?php

use function Cosray\escape;

$this->layout('layer/main');

$system = (array) $this->unwrap($system);
$panelBase = (string) $panelBase;
$defaultLocale = (string) ($system['defaultLocale'] ?? '');
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>

<div
	class="page cms-media"
	data-content-locale-scope
	data-content-locale="<?= escape($defaultLocale) ?>">
	<header class="head">
		<div class="titles">
			<div class="line">
				<h1><?= escape(__('media:title')) ?></h1>
				<?php // The count belongs to the listing, which the island owns. ?>
				<span class="cms-count" data-media-count hidden></span>
			</div>
		</div>
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
