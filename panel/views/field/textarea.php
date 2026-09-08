<?php

use function Cosray\escape;

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$value = $this->unwrap($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
$iframe = ($control['name'] ?? '') === 'iframe';
$placeholder = $field['placeholder'] ?? null;
$lines = $field['lines'] ?? null;
$fallbackPreview ??= false;
?>
<textarea
	<?= $fallbackPreview ? 'data-fallback-input data-schema-placeholder="' . escape((string) $placeholder) . '"' : '' ?>
	class="cms-textarea<?= $iframe ? ' iframe' : '' ?>"
	id="<?= escape($id) ?>"
	name="<?= escape($name) ?>"
	<?= is_string($placeholder) && $placeholder !== '' ? 'placeholder="' . escape($placeholder) . '"' : '' ?>
	<?= is_int($lines) && $lines > 0 ? 'rows="' . $lines . '"' : '' ?>
	<?= $field['required'] ?? false ? 'required' : '' ?>
	<?= $field['immutable'] ?? false ? 'disabled' : '' ?>><?= escape($value) ?></textarea>
