<?php

use function Cosray\escape;

$field = (array) u($field);
$control = (array) u($control);
$value = u($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
$iframe = ($control['name'] ?? '') === 'iframe';
$placeholder = $field['placeholder'] ?? null;
$lines = $field['lines'] ?? null;
$rows = is_int($lines) && $lines > 0 ? $lines : 1;
$fallbackPreview ??= false;
?>
<textarea
	<?= $fallbackPreview ? 'data-fallback-input data-schema-placeholder="' . escape((string) $placeholder) . '"' : '' ?>
	class="cms-textarea<?= $iframe ? ' iframe' : '' ?>"
	id="<?= escape($id) ?>"
	name="<?= escape($name) ?>"
	<?= is_string($placeholder) && $placeholder !== '' ? 'placeholder="' . escape($placeholder) . '"' : '' ?>
	rows="<?= $rows ?>"
	<?= $field['required'] ?? false ? 'required' : '' ?>
	<?= $field['immutable'] ?? false ? 'readonly' : '' ?>><?= escape($value) ?></textarea>
