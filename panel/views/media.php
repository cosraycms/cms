<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('panel');
}

$system = (array) $this->unwrap($system);
$panelBase = (string) $panelBase;
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>

<div id="main" class="page cms-media">
	<header class="head">
		<h1><?= escape(__('media:title')) ?></h1>
	</header>

	<section class="body">
		<cosray-media-library data-cosray-element="media-library"></cosray-media-library>
	</section>

	<script id="cosray-system-data" type="application/json"><?= json_encode(
	['panel' => $panelBase, 'system' => $system],
	$jsonFlags,
) ?></script>
</div>
