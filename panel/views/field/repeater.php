<?php

use function Cosray\escape;

// Repeating item control; value is a neutral-locale list. Add/remove is
// wired up by the repeater behavior, which clones the __i__ template
// row and renumbers names, ids and labels.

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$item = (array) ($props['item'] ?? []);
$value = $this->unwrap($value ?? null);
$items = is_array($value) ? array_values($value) : [];
$max = $props['max'] ?? null;
$subField = ['required' => false, 'immutable' => false];

$row = function (int|string $index, mixed $itemValue) use ($item, $subField, $id, $name): void {
	$itemId = "{$id}-{$index}";
	?>
	<div class="cms-repeater-item" data-repeater-row>
		<div class="cms-repeater-item-control">
			<label class="cms-sub-label" for="<?= escape($itemId) ?>" data-repeater-label>
				<?= is_int($index) ? ($index + 1) . '.' : '' ?>
			</label>
			<?php $this->insert('field/control', [
				'field' => $subField,
				'control' => $item,
				'id' => $itemId,
				'name' => "{$name}[{$index}]",
				'value' => $itemValue,
				'data' => null,
			]) ?>
		</div>
		<button type="button" class="cms-button" data-repeater-remove>
			<?= escape(__('field:remove')) ?>
		</button>
	</div>
	<?php
};
?>
<div
	class="cms-repeater"
	data-repeater
	data-name="<?= escape($name) ?>"
	data-id="<?= escape($id) ?>"
	<?= is_int($max) ? 'data-max="' . $max . '"' : '' ?>>
	<?php foreach ($items as $index => $itemValue) {
		$row($index, $itemValue);
	} ?>
	<template data-repeater-template>
		<?php $row('__i__', null) ?>
	</template>
	<div data-repeater-footer>
		<button
			type="button"
			class="cms-button"
			data-repeater-add
			<?= is_int($max) && count($items) >= $max ? 'hidden' : '' ?>>
			<?= escape(__('field:add')) ?>
		</button>
	</div>
</div>
