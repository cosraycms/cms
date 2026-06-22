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
$parent = trim((string) $parent);
$parent = $parent === '' ? null : $parent;
$queryState = $queryState instanceof Traversable ? iterator_to_array($queryState) : (array) $queryState;
$queryState = [
	'q' => (string) ($queryState['q'] ?? ''),
	'sort' => (string) ($queryState['sort'] ?? ''),
	'dir' => (string) ($queryState['dir'] ?? ''),
	'offset' => (int) (string) ($queryState['offset'] ?? 0),
	'limit' => (int) (string) ($queryState['limit'] ?? 50),
	'parent' => $parent,
];
$editorAssets = $editorAssets instanceof Traversable
	? iterator_to_array($editorAssets)
	: (array) $editorAssets;
$editorAssets = trim((string) ($editorAssets['js'] ?? '')) === ''
	? null
	: [
		'js' => (string) $editorAssets['js'],
		'css' => trim((string) ($editorAssets['css'] ?? '')) === ''
			? null
			: (string) $editorAssets['css'],
	];
$panelPath = (string) $panelPath;
$legacyPanelPath = (string) $legacyPanelPath;
$legacyApiBase = (string) $legacyApiBase;
$legacyBootUrl = (string) $legacyBootUrl;
$collectionPath = $panelPath . '/collection/' . rawurlencode($slug);
$legacyCollectionPath = $legacyPanelPath . '/collection/' . rawurlencode($slug);

$queryUrl = static function (string $path, array $query): string {
	$params = array_filter(
		$query,
		static fn(mixed $value): bool => $value !== null && $value !== '',
	);

	if (($params['offset'] ?? null) === 0) {
		unset($params['offset']);
	}

	if (($params['limit'] ?? null) === 50) {
		unset($params['limit']);
	}

	$queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

	return $queryString === '' ? $path : $path . '?' . $queryString;
};

$backUrl = $queryUrl($collectionPath, $queryState);
$legacyUrl = $nodeUid === null
	? $legacyCollectionPath
	: $queryUrl($legacyCollectionPath . '/' . rawurlencode($nodeUid), $queryState);
$bootstrap = [
	'mode' => $mode,
	'collection' => [
		'name' => $name,
		'slug' => $slug,
		'q' => (string) ($queryState['q'] ?? ''),
		'offset' => (int) ($queryState['offset'] ?? 0),
		'limit' => (int) ($queryState['limit'] ?? 50),
		'sort' => (string) ($queryState['sort'] ?? ''),
		'dir' => (string) ($queryState['dir'] ?? ''),
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
	<header class="topbar editor-topbar">
		<a class="btn btn-ghost" href="<?= escape($backUrl) ?>" hx-target="#main">Back to list</a>
		<h1><?= escape($name) ?></h1>
	</header>

	<section class="content editor-content">
		<?php if ($editorAssets !== null): ?>
			<?php if (is_string($editorAssets['css'] ?? null)): ?>
				<link rel="stylesheet" href="<?= escape($editorAssets['css']) ?>">
			<?php endif ?>
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
			<script type="module" src="<?= escape((string) $editorAssets['js']) ?>"></script>
		<?php else: ?>
			<div class="editor-fallback">
				<h2>Editor bundle missing</h2>
				<p>Build the Svelte editor island before using the new panel editor.</p>
				<code>cd ui &amp;&amp; pnpm run build:editor</code>
				<a class="btn btn-ghost" href="<?= escape($legacyUrl) ?>" hx-boost="false">
					Open legacy editor
				</a>
			</div>
		<?php endif ?>
	</section>
</div>
