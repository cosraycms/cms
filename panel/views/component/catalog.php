<?php

// The panel catalog for the verba runtime in the browser. It has to survive a
// history restore, where htmx replaces the whole body: lazily loaded element
// bundles read it when they first run, which can be long after boot.

$catalog = (array) $this->unwrap(
	$messages ?? ['locale' => (string) ($localeId ?? 'en'), 'domains' => []],
);

?>
<script id="verba-catalog" type="application/json"><?= json_encode(
	$catalog,
	JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT,
) ?></script>
