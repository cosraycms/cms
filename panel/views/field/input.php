<?php

use function Cosray\escape;

// Shared native input renderer. Receives: field, id, name, value, type
// and optional attrs (step, min, max, placeholder).

$field = (array) u($field);
$attrs = (array) (u($attrs ?? null) ?? []);
$fallbackPreview ??= false;
$value = u($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
?>
<input
	class="cms-input"
	<?= $fallbackPreview ? 'data-fallback-input data-schema-placeholder="' . escape((string) ($attrs['placeholder'] ?? '')) . '"' : '' ?>
	id="<?= escape($id) ?>"
	name="<?= escape($name) ?>"
	type="<?= escape($type) ?>"
	value="<?= escape($value) ?>"
	<?php foreach ($attrs as $attr => $attrValue): ?>
		<?php if ($attrValue !== null && $attrValue !== ''): ?>
			<?= $attr ?>="<?= escape((string) $attrValue) ?>"
		<?php endif ?>
	<?php endforeach ?>
	<?= $field['required'] ?? false ? 'required' : '' ?>
	<?= $field['immutable'] ?? false ? 'readonly' : '' ?> />
