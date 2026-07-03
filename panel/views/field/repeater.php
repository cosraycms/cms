<?php

use function Cosray\escape;

// Repeating item control; value is a neutral-locale list. Add/remove and
// reordering are wired up by the repeater behavior (template based).

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$item = (array) ($props['item'] ?? []);
$value = $this->unwrap($value ?? null);
$items = is_array($value) ? array_values($value) : [];
$subField = ['required' => false, 'immutable' => false];
?>
<div class="cms-repeater">
	<?php foreach ($items as $index => $itemValue): ?>
		<?php $itemId = "{$id}-{$index}"; ?>
		<div class="cms-repeater-item">
			<div class="cms-repeater-item-control">
				<label class="cms-sub-label" for="<?= escape($itemId) ?>"><?= $index + 1 ?>.</label>
				<?php $this->insert('field/control', [
					'field' => $subField,
					'control' => $item,
					'id' => $itemId,
					'name' => "{$name}[{$index}]",
					'value' => $itemValue,
					'data' => null,
				]) ?>
			</div>
		</div>
	<?php endforeach ?>
</div>
