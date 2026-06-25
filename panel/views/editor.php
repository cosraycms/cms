<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('panel');
}

$mode = (string) $mode;
$name = (string) $name;
$slug = (string) $slug;
$nodeUid = trim((string) $nodeUid);
$nodeUid = $nodeUid === '' ? null : $nodeUid;
$type = trim((string) $type);
$type = $type === '' ? null : $type;
$parent = $queryState->parent;
$editorAvailable = (bool) $editorAvailable;
$panelPath = (string) $panelPath;
$legacyApiBase = (string) $legacyApiBase;
$legacyBootUrl = (string) $legacyBootUrl;

if ($nodeUid !== null) {
	$legacyUrl = $legacyLinks->edit($nodeUid);
} elseif ($type !== null) {
	$legacyUrl = $legacyLinks->create($type);
} else {
	$legacyUrl = $legacyLinks->collection();
}
$bootstrap = [
	'mode' => $mode,
	'collection' => [
		'name' => $name,
		'slug' => $slug,
		'q' => $queryState->q,
		'offset' => $queryState->offset,
		'limit' => $queryState->limit,
		'sort' => $queryState->sort,
		'dir' => $queryState->dir,
		'parent' => $parent,
	],
	'node' => $nodeUid,
	'type' => $type,
	'parent' => $parent,
	'apiBase' => $legacyApiBase,
	'bootUrl' => $legacyBootUrl,
	'panelPath' => $panelPath,
];
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$bootstrapJson = json_encode($bootstrap, $jsonFlags);
$panelBase = $panelPath === '/' ? '/' : rtrim($panelPath, '/') . '/';
$runtimeJson = json_encode([
	'base' => $panelBase,
	'api' => $legacyApiBase,
	'boot' => $legacyBootUrl,
	'login' => $panelPath . '/login',
], $jsonFlags);
?>

<div id="main" class="page node">
	<section class="editor-content">
		<?php if ($editorAvailable): ?>
			<div
				id="cosray-node-editor"
				class="editor-host"
				data-cosray-node-editor>
				<div class="cms-node-placeholder" aria-hidden="true" inert>
					<div class="sticky">
						<div class="bar">
							<div class="inner">
								<div class="trail">
									<span class="icon"></span>
									<span class="chevron"></span>
									<span class="collection"><?= escape($name) ?></span>
								</div>
								<div class="actions">
									<span class="button danger"></span>
									<span class="button secondary"></span>
									<span class="button primary"></span>
								</div>
							</div>
						</div>
						<div class="header-frame">
							<div class="headline">
								<span class="title"></span>
								<span class="status"></span>
							</div>
							<div class="tabs">
								<span class="tab active"></span>
								<span class="tab"></span>
							</div>
						</div>
					</div>
					<div class="body">
						<div class="card">
							<span class="line short"></span>
							<span class="line long"></span>
							<span class="line medium"></span>
						</div>
					</div>
				</div>
				<p class="cms-node-placeholder-status" role="status">Loading editor …</p>
			</div>
			<script id="cosray-node-editor-data" type="application/json"><?= $bootstrapJson ?></script>
			<script>
				{
					const runtime = <?= $runtimeJson ?>;
					window.COSRAY_BASE_PATH = runtime.base;
					window.COSRAY_API_BASE = runtime.api;
					window.COSRAY_BOOT_URL = runtime.boot;
					window.COSRAY_LOGIN_URL = runtime.login;
				}
			</script>
		<?php else: ?>
			<div class="editor-fallback">
				<h2>Panel bundle missing</h2>
				<p>Build the hybrid panel and copy the build output to public<?= escape($panelPath) ?>/build.</p>
				<code>public<?= escape($panelPath) ?>/build/panel.js</code>
				<a class="cms-button secondary" href="<?= escape($legacyUrl) ?>" hx-boost="false">
					Open legacy editor
				</a>
			</div>
		<?php endif ?>
	</section>
</div>
