<?php

use function Cosray\escape;

// Debounced route-path preview fragment: carries the paths the current form
// state would generate as data for the paths behavior. The path inputs stay
// out of the swap so a refresh cannot disturb typing.

$paths = (array) $this->unwrap($paths);
?>
<div
	id="generated-paths"
	hidden
	data-paths="<?= escape(json_encode(
		(object) $paths,
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
	)) ?>"
	hx-post="<?= escape((string) $pathsUrl) ?>"
	hx-trigger="input from:.js-path-source delay:500ms, change from:.js-path-source delay:500ms"
	hx-include="#node-editor-form"
	<?php // Swaps itself, so it has to opt out of the target the content region inherits. ?>
	hx-target="this"
	hx-swap="outerHTML"></div>
