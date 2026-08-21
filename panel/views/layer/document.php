<?php

use function Cosray\escape;

$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

?>
<!DOCTYPE html>
<html lang="<?= escape((string) ($localeId ?? 'en')) ?>">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?= escape(__('panel:title')) ?></title>
	<style>@layer tokens, reset, panel, plugin, theme;</style>
<?php foreach ($stylesheets as $stylesheet): ?>
	<link rel="stylesheet" href="<?= escape((string) $stylesheet) ?>">
<?php endforeach ?>
</head>

<body hx-boost:inherited="true">
	<?= $this->body() ?>

	<?php $this->insert('component/catalog') ?>

	<?php // Element control modules resolve against this base. It has to be set

	// before the panel module runs: a boosted navigation upgrades the custom
	// elements in the swapped markup as they are inserted, which is before any
	// swap handler could read the editor payload. ?>
	<script>window.COSRAY_BASE_PATH = <?= json_encode(
		(string) $panelBase,
		$jsonFlags,
	) ?>;</script>

<?php foreach ($scripts as $script): ?>
	<script src="<?= escape((string) $script) ?>"></script>
<?php endforeach ?>
<?php foreach ($moduleScripts as $script): ?>
	<script type="module" src="<?= escape((string) $script) ?>"></script>
<?php endforeach ?>
</body>
</html>
