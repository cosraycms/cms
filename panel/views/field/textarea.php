<?php

use function Cosray\escape;

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$value = $this->unwrap($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
$iframe = ($control['name'] ?? '') === 'iframe';
$placeholder = $field['placeholder'] ?? null;
?>
<textarea
	class="cms-textarea<?= $iframe ? ' iframe' : '' ?>"
	id="<?= escape($id) ?>"
	name="<?= escape($name) ?>"
	<?= is_string($placeholder) && $placeholder !== '' ? 'placeholder="' . escape($placeholder) . '"' : '' ?>
	<?= $field['required'] ?? false ? 'required' : '' ?>
	<?= $field['immutable'] ?? false ? 'disabled' : '' ?>><?= escape($value) ?></textarea>
