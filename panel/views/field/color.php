<?php

use Cosray\Value\Color;

use function Cosray\escape;

// Hex colour. The named text input carries the value, so the field can be
// empty and a colour can be pasted; a native colour input cannot be empty.
// The unnamed picker behind the swatch and the palette buttons only
// write into the text input.
// Receives: field, id, name, value.

$field = (array) u($field);
$value = u($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
$color = Color::normalize($value);
$readonly = (bool) ($field['immutable'] ?? false);
$palette = array_map(
	static fn($option) => (
		is_array($option)
			? ['value' => (string) $option['value'], 'label' => (string) $option['label']]
			: ['value' => (string) $option, 'label' => null]
	),
	(array) ($field['options'] ?? []),
);
?>
<div class="cms-color" data-color>
	<span class="cms-color-swatch" <?= $color === null ? 'data-empty' : 'style="--color: ' . $color . '"' ?>>
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
	<?php if ($palette !== []): ?>
		<div class="cms-color-palette" role="group" aria-label="<?= escape(__('color:palette')) ?>">
			<?php foreach ($palette as $option): ?>
				<?php $title = $option['label'] === null ? $option['value'] : "{$option['label']} ({$option['value']})"; ?>
				<button
					type="button"
					class="cms-color-swatch"
					style="--color: <?= escape($option['value']) ?>"
					data-color-option="<?= escape($option['value']) ?>"
					aria-label="<?= escape($title) ?>"
					aria-pressed="<?= $option['value'] === $color ? 'true' : 'false' ?>"
					title="<?= escape($title) ?>"
					<?= $readonly ? 'disabled' : '' ?>></button>
			<?php endforeach ?>
		</div>
	<?php endif ?>
</div>
