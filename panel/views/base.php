<?php

use function Cosray\escape;

$catalog = $messages ?? ['plural' => (string) ($localeId ?? 'en'), 'messages' => []];
$catalog['messages'] = (object) $catalog['messages'];
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

?>
<!DOCTYPE html>
<html lang="<?= escape((string) ($localeId ?? 'en')) ?>">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Cosray CMS Panel</title>
	<style>@layer tokens, reset, panel, plugin, theme;</style>
<?php foreach ($stylesheets as $stylesheet): ?>
	<link rel="stylesheet" href="<?= escape((string) $stylesheet) ?>">
<?php endforeach ?>
</head>

<body hx-boost:inherited="true">
	<?= $this->body() ?>

	<script id="cosray-messages" type="application/json"><?= json_encode($catalog, $jsonFlags) ?></script>

<?php foreach ($scripts as $script): ?>
	<script src="<?= escape((string) $script) ?>"></script>
<?php endforeach ?>
<?php foreach ($moduleScripts as $script): ?>
	<script type="module" src="<?= escape((string) $script) ?>"></script>
<?php endforeach ?>
</body>
</html>
