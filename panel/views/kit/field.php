<?php

use function Cosray\escape;

// A form field of the panel kit: label, control, description and error.
// Receives: name, label, and optional type (text, textarea, select, file
// or another input type; text by default), value, options (value =>
// label, for select), description, error, placeholder, rows, required,
// accept, form (the id of the form it belongs to) and id.

$name = (string) u($name);
$type = (string) u($type ?? 'text');
$id = (string) u($id ?? 'field-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $name));
$value = (string) u($value ?? '');
$description = u($description ?? null);
$error = u($error ?? null);
$placeholder = u($placeholder ?? null);
$described = trim((is_string($description) ? "{$id}-description " : '') . (is_string($error) ? "{$id}-error" : ''));
$attributes = ' id="' . escape($id) . '" name="' . escape($name) . '"';
$attributes .= is_string($placeholder) ? ' placeholder="' . escape($placeholder) . '"' : '';
$attributes .= u($required ?? false) ? ' required' : '';
$attributes .= isset($form) ? ' form="' . escape((string) u($form)) . '"' : '';
$attributes .= $described !== '' ? ' aria-describedby="' . escape($described) . '"' : '';
$attributes .= is_string($error) ? ' aria-invalid="true"' : '';
?>
<div class="cms-kit-field"<?= is_string($error) ? ' data-invalid' : '' ?>>
	<label for="<?= escape($id) ?>"><?= escape((string) u($label)) ?></label>
	<?php if ($type === 'textarea'): ?>
		<textarea class="cms-textarea" rows="<?= (int) u($rows ?? 3) ?>"<?= $attributes ?>><?= escape($value) ?></textarea>
	<?php elseif ($type === 'select'): ?>
		<select class="cms-select"<?= $attributes ?>>
			<?php foreach ((array) u($options ?? []) as $option => $optionLabel): ?>
				<option value="<?= escape((string) $option) ?>"<?= (string) $option === $value ? ' selected' : '' ?>><?= escape((string) $optionLabel) ?></option>
			<?php endforeach ?>
		</select>
	<?php else: ?>
		<input
			class="cms-input"
			type="<?= escape($type) ?>"
			<?= $type === 'file' ? '' : 'value="' . escape($value) . '"' ?>
			<?= isset($accept) ? 'accept="' . escape((string) u($accept)) . '"' : '' ?><?= $attributes ?> />
	<?php endif ?>
	<?php if (is_string($description)): ?>
		<p class="cms-kit-field-description" id="<?= escape($id) ?>-description"><?= escape($description) ?></p>
	<?php endif ?>
	<?php if (is_string($error)): ?>
		<p class="cms-kit-field-error" id="<?= escape($id) ?>-error"><?= escape($error) ?></p>
	<?php endif ?>
</div>
