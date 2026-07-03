<?php

use function Cosray\escape;

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$value = $this->unwrap($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
$required = (bool) ($field['required'] ?? false);
$disabled = (bool) ($field['immutable'] ?? false);

// Sub-controls inside group/repeater carry options in the descriptor
// props; top-level option fields expose them on the field (#[Options]).
$options = array_map(
	static fn($option) => (
		is_array($option)
			? ['value' => (string) $option['value'], 'label' => (string) $option['label']]
			: ['value' => (string) $option, 'label' => (string) $option]
	),
	(array) ($props['options'] ?? $field['options'] ?? []),
);
?>
<?php if (($props['display'] ?? 'select') === 'radio'): ?>
	<div class="cms-radio-group" id="<?= escape($id) ?>">
		<?php foreach ($options as $option): ?>
			<label class="cms-radio-option">
				<input
					type="radio"
					class="cms-radio"
					name="<?= escape($name) ?>"
					value="<?= escape($option['value']) ?>"
					<?= $option['value'] === $value ? 'checked' : '' ?>
					<?= $required ? 'required' : '' ?>
					<?= $disabled ? 'disabled' : '' ?> />
				<?= escape($option['label']) ?>
			</label>
		<?php endforeach ?>
	</div>
<?php else: ?>
	<select
		class="cms-select"
		id="<?= escape($id) ?>"
		name="<?= escape($name) ?>"
		<?= $required ? 'required' : '' ?>
		<?= $disabled ? 'disabled' : '' ?>>
		<?php foreach ($options as $option): ?>
			<option
				value="<?= escape($option['value']) ?>"
				<?= $option['value'] === $value ? 'selected' : '' ?>>
				<?= escape($option['label']) ?>
			</option>
		<?php endforeach ?>
	</select>
<?php endif ?>
