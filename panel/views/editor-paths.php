<?php

use function Cosray\escape;

// Debounced route-path preview fragment: shows the paths the current
// form state would generate for locales without an explicit path.

$paths = (array) $this->unwrap($paths);
$submitted = (array) $this->unwrap($submitted);
?>
<div
	id="generated-paths"
	class="cms-settings-generated"
	hx-post="<?= escape((string) $pathsUrl) ?>"
	hx-trigger="input from:#node-editor-form delay:500ms"
	hx-include="#node-editor-form"
	hx-swap="outerHTML">
	<?php foreach ($paths as $locale => $path): ?>
		<?php if (
			is_string($path)
			&& $path !== ''
			&& trim((string) ($submitted[$locale] ?? '')) === ''
		): ?>
			<div class="cms-generated-path">
				<span class="cms-generated-path-locale"><?= escape(strtoupper((string) $locale)) ?>:</span>
				<code><?= escape($path) ?></code>
			</div>
		<?php endif ?>
	<?php endforeach ?>
</div>
