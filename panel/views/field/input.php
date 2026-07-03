<?php

use function Cosray\escape;

// Shared native input renderer. Receives: field, id, name, value, type
// and optional attrs (step, min, max, placeholder).

$field = (array) $this->unwrap($field);
$attrs = (array) ($this->unwrap($attrs) ?? []);
$value = $this->unwrap($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
?>
<input
	class="cms-input"
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
	<?= $field['immutable'] ?? false ? 'disabled' : '' ?> />
