<?php

use Cosray\Value\Color;

use function Cosray\escape;

// Hex colour. The named text input carries the value, so the field can be
// empty and a colour can be pasted; a native colour input cannot be empty.
// The unnamed picker behind the swatch only writes into the text input.
// Receives: field, id, name, value.

$field = (array) $this->unwrap($field);
$value = $this->unwrap($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
$color = Color::normalize($value);
$readonly = (bool) ($field['immutable'] ?? false);
?>
<div class="cms-color" data-color>
	<span class="swatch" <?= $color === null ? 'data-empty' : 'style="--color: ' . $color . '"' ?>>
		<input
			type="color"
			data-color-picker
			value="<?= $color ?? '#000000' ?>"
			aria-label="<?= escape(__('color:pick')) ?>"
			<?= $readonly ? 'disabled' : '' ?> />
	</span>
	<input
		class="cms-input"
		type="text"
		id="<?= escape($id) ?>"
		name="<?= escape($name) ?>"
		value="<?= escape($value) ?>"
		data-color-value
		autocomplete="off"
		spellcheck="false"
		<?= $field['required'] ?? false ? 'required' : '' ?>
		<?= $readonly ? 'readonly' : '' ?> />
</div>
