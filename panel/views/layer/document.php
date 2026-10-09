<?php

use function Cosray\escape;

$catalog = (array) $this->unwrap(
	$messages ?? ['locale' => (string) ($localeId ?? 'en'), 'domains' => []],
);
$importMap = (array) $this->unwrap($importMap ?? []);
$liveReload = $this->unwrap($liveReload ?? null);
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

?>
<!DOCTYPE html>
<html lang="<?= escape((string) ($localeId ?? 'en')) ?>">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?= escape(__('panel:title')) ?></title>
<?php if (is_string($liveReload) && $liveReload !== ''): ?>
	<?php /* Morphing would strip the markup the element controls render
	 * themselves, so the dev server's live reload reloads the panel. */ ?>
	<meta name="celema-live-reload" content="reload">
<?php endif ?>
	<style>@layer tokens, reset, panel, plugin, theme;</style>
<?php if (($importMap['imports'] ?? []) !== []): ?>
	<script type="importmap"><?= json_encode($importMap, $jsonFlags) ?></script>
<?php endif ?>
<?php foreach ($modulePreloads ?? [] as $preload): ?>
	<link rel="modulepreload" href="<?= escape((string) $preload) ?>">
<?php endforeach ?>
<?php foreach ($stylesheets as $stylesheet): ?>
	<link rel="stylesheet" href="<?= escape((string) $stylesheet) ?>">
<?php endforeach ?>
</head>

<body hx-boost:inherited="true">
	<?= $this->slot() ?>

	<script id="verba-catalog" type="application/json"><?= json_encode($catalog, $jsonFlags) ?></script>

	<?php /* The runtime module reads these bases once, when the first panel
	 * module loads; element controls resolve their modules and the script-built
	 * icons against them. They are inline globals of the document because a
	 * boosted navigation upgrades the custom elements in the swapped markup as
	 * it is inserted, before any swap handler could pass them along. */ ?>
	<script>window.COSRAY_BASE_PATH = <?= json_encode(
		(string) $panelBase,
		$jsonFlags,
	) ?>;
	window.COSRAY_ASSETS_PATH = <?= json_encode((string) $assetsBase, $jsonFlags) ?>;</script>

<?php foreach ($scripts as $script): ?>
	<script src="<?= escape((string) $script) ?>"></script>
<?php endforeach ?>
<?php foreach ($moduleScripts as $script): ?>
	<script type="module" src="<?= escape((string) $script) ?>"></script>
<?php endforeach ?>
<?php if (is_string($liveReload) && $liveReload !== ''): ?>
	<script src="<?= escape($liveReload) ?>" defer></script>
<?php endif ?>
</body>
</html>
