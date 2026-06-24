<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('app');
}

$mode = (string) $mode;
$name = (string) $name;
$slug = (string) $slug;
$nodeUid = trim((string) $nodeUid);
$nodeUid = $nodeUid === '' ? null : $nodeUid;
$type = trim((string) $type);
$type = $type === '' ? null : $type;
$parent = $queryState->parent;
$editorAssets = $editorAssets instanceof Traversable
	? iterator_to_array($editorAssets)
	: (array) $editorAssets;
$editorScripts = $editorAssets['scripts'] ?? [];
$editorStylesheets = $editorAssets['stylesheets'] ?? [];
$editorScripts = $editorScripts instanceof Traversable
	? iterator_to_array($editorScripts)
	: (array) $editorScripts;
$editorStylesheets = $editorStylesheets instanceof Traversable
	? iterator_to_array($editorStylesheets)
	: (array) $editorStylesheets;
$editorScripts = array_values(array_filter(
	array_map(static fn(mixed $script): string => trim((string) $script), $editorScripts),
	static fn(string $script): bool => $script !== '',
));
$editorStylesheets = array_values(array_filter(
	array_map(static fn(mixed $stylesheet): string => trim((string) $stylesheet), $editorStylesheets),
	static fn(string $stylesheet): bool => $stylesheet !== '',
));
$hasEditorAssets = $editorScripts !== [];
$panelPath = (string) $panelPath;
$legacyApiBase = (string) $legacyApiBase;
$legacyBootUrl = (string) $legacyBootUrl;

$backUrl = $links->back();
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
	],
	'node' => $nodeUid,
	'type' => $type,
	'parent' => $parent,
	'apiBase' => $legacyApiBase,
	'bootUrl' => $legacyBootUrl,
	'panelPath' => $panelPath,
	'backUrl' => $backUrl,
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

<div id="main" class="page editor-page">
	<header class="topbar topbar-editor">
		<div class="content">
			<a class="btn btn-ghost" href="<?= escape($backUrl) ?>" hx-target="#main">Back to list</a>
			<h1><?= escape($name) ?></h1>
		</div>
	</header>

	<section class="content editor-content">
		<?php if ($hasEditorAssets): ?>
			<?php foreach ($editorStylesheets as $stylesheet): ?>
				<link rel="stylesheet" href="<?= escape($stylesheet) ?>">
			<?php endforeach ?>
			<div
				id="cosray-node-editor"
				class="editor-host"
				data-cosray-node-editor>
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
			<?php foreach ($editorScripts as $script): ?>
				<script type="module" src="<?= escape($script) ?>"></script>
			<?php endforeach ?>
		<?php else: ?>
			<div class="editor-fallback">
				<h2>Editor bundle missing</h2>
				<p>Build the Svelte editor island before using the new panel editor.</p>
				<code>cd panel &amp;&amp; pnpm run build</code>
				<a class="btn btn-ghost" href="<?= escape($legacyUrl) ?>" hx-boost="false">
					Open legacy editor
				</a>
			</div>
		<?php endif ?>
	</section>
</div>
