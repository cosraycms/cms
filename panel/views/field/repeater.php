<?php

// Repeating item control; value is a neutral-locale list. Add/remove is
// wired up by the repeater behavior, which clones the __i__ template
// row and renumbers names, ids and labels.

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$item = (array) ($props['item'] ?? []);
$value = $this->unwrap($value ?? null);
$items = is_array($value) ? array_values($value) : [];
$max = $props['max'] ?? null;
?>
<div
	class="cms-repeater"
	data-repeater
	data-name="<?= $this->escape($name) ?>"
	data-id="<?= $this->escape($id) ?>"
	<?= is_int($max) ? 'data-max="' . $max . '"' : '' ?>>
	<?php foreach ($items as $index => $itemValue): ?>
		<?php $this->insert('field/repeater/row', [
			'index' => $index,
			'itemValue' => $itemValue,
			'item' => $item,
		]) ?>
	<?php endforeach ?>
	<template data-repeater-template>
		<?php $this->insert('field/repeater/row', [
			'index' => '__i__',
			'itemValue' => null,
			'item' => $item,
		]) ?>
	</template>
	<div data-repeater-footer>
		<button
			type="button"
			class="cms-button"
			data-repeater-add
			<?= is_int($max) && count($items) >= $max ? 'hidden' : '' ?>>
			<?= $this->escape(__('field:add')) ?>
		</button>
	</div>
</div>
