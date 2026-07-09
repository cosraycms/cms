<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('panel');
}

$system = (array) $this->unwrap($system);
$panelPath = (string) $panelPath;
$panelBase = $panelPath === '/' ? '/' : rtrim($panelPath, '/') . '/';
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>

<div id="main" class="page media-page">
	<header class="topbar">
		<div class="inner">
			<h1><?= escape(__('media:title')) ?></h1>
		</div>
	</header>

	<section class="content">
		<cosray-media-library data-cosray-element="media-library"></cosray-media-library>
	</section>

	<script id="cosray-system-data" type="application/json"><?= json_encode(
	['panel' => $panelBase, 'system' => $system],
	$jsonFlags,
) ?></script>
</div>
