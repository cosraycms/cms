<?php

$index = $this->unwrap($index);
$itemValue = $this->unwrap($itemValue ?? null);
$item = (array) $this->unwrap($item);
$id = (string) $this->unwrap($id);
$name = (string) $this->unwrap($name);
$itemId = "{$id}-{$index}";
$subField = ['required' => false, 'immutable' => false];
?>
<div class="cms-repeater-item" data-repeater-row>
	<div class="cms-repeater-item-control">
		<label class="cms-sub-label" for="<?= $this->escape($itemId) ?>" data-repeater-label>
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
		<?= $this->escape(__('field:remove')) ?>
	</button>
</div>
