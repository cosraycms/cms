<?php

use function Cosray\escape;

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$labels = (array) ($props['labels'] ?? []);
$labels += [
	'true' => __('field:yes'),
	'false' => __('field:no'),
	'null' => __('field:not-set'),
];
$immutable = (bool) ($field['immutable'] ?? false);
$required = !$immutable && (bool) ($field['required'] ?? false);
$value = $this->unwrap($value ?? null);
?>
<?php if ($props['nullable'] ?? false): ?>
	<div
		class="cms-tristate"
		id="<?= escape($id) ?>"
		role="radiogroup"
		aria-labelledby="<?= escape($id) ?>-label"
		data-checkbox>
		<?php foreach (['null' => null, 'true' => true, 'false' => false] as $state => $choice): ?>
			<label class="option">
				<input
					type="radio"
					class="sr-only"
					name="<?= escape($name) ?>"
					value="<?= $choice === null ? '' : ($choice ? '1' : '0') ?>"
					<?= $value === $choice && !($required && $choice === null) ? 'checked' : '' ?>
					<?= $required ? 'required' : '' ?>
					<?= $immutable || $required && $choice === null ? 'disabled' : '' ?> />
				<span><?= escape($labels[$state]) ?></span>
			</label>
		<?php endforeach ?>
	</div>
<?php else: ?>
	<div class="cms-toggle">
		<?php // Immutable fields omit the unchecked presence marker as well as disabling the input. ?>
		<?php if (!$immutable): ?>
			<input type="hidden" name="<?= escape($name) ?>" value="" />
		<?php endif ?>
		<label class="toggle">
			<input
				id="<?= escape($id) ?>"
				name="<?= escape($name) ?>"
				type="checkbox"
				role="switch"
				class="cms-switch"
				aria-labelledby="<?= escape($id) ?>-label"
				aria-describedby="<?= escape($id) ?>-status"
				value="1"
				<?= $value ? 'checked' : '' ?>
				<?= $immutable ? 'disabled' : '' ?> />
			<span class="status" id="<?= escape($id) ?>-status">
				<span class="on"><?= escape($labels['true']) ?></span>
				<span class="off"><?= escape($labels['false']) ?></span>
			</span>
		</label>
	</div>
<?php endif ?>
