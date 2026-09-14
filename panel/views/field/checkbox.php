<?php

use function Cosray\escape;

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$labels = (array) ($control['props']['labels'] ?? []);
$immutable = (bool) ($field['immutable'] ?? false);
$value = $this->unwrap($value ?? null);
?>
<div class="cms-toggle">
	<?php // Without the marker, an unchecked input would leave the stored value untouched.

	// Immutable fields must omit it as well as disabling their input. ?>
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
			value="1"
			<?= $value ? 'checked' : '' ?>
			<?= $immutable ? 'disabled' : '' ?> />
		<span class="status" aria-hidden="true">
			<span class="on"><?= escape($labels['true'] ?? __('field:yes')) ?></span>
			<span class="off"><?= escape($labels['false'] ?? __('field:no')) ?></span>
		</span>
	</label>
</div>
