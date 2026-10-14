<?php

$index = u($index);
$itemValue = u($itemValue ?? null);
$item = (array) u($item);
$id = (string) u($id);
$name = (string) u($name);
$itemId = "{$id}-{$index}";
$subField = ['required' => false, 'immutable' => false];
?>
<div class="cms-repeater-item" data-repeater-row>
	<div class="cms-repeater-item-control">
		<label class="cms-sub-label" id="<?= $this->escape($itemId) ?>-label" for="<?= $this->escape(
			$itemId,
		) ?>" data-repeater-label>
			<?= is_int($index) ? ($index + 1) . '.' : '' ?>
		</label>
		<?php $this->include('field/control', [
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
