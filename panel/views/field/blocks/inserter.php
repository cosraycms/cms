<?php

// The + that puts a new block before the row it sits in. One offered
// type inserts at once; several open the type picker.

$blockTypes = (array) $this->unwrap($blockTypes);
$single = $this->unwrap($single ?? null);
$single = is_string($single) ? $single : null;
$insert = (string) $this->unwrap($insert);
$label = (string) $this->unwrap($label);
?>
<?php if ($single !== null): ?>
	<button
		type="button"
		class="inserter"
		data-repeater-add="<?= $this->escape($single) ?>"
		data-repeater-insert="<?= $this->escape($insert) ?>"
		aria-label="<?= $this->escape($label) ?>"
		title="<?= $this->escape($label) ?>"></button>
<?php else: ?>
	<details class="inserter picker" data-repeater-menu>
		<summary aria-label="<?= $this->escape($label) ?>" title="<?= $this->escape($label) ?>"></summary>
		<div class="picker-menu">
			<?php $this->insert('field/blocks/picker', [
				'blockTypes' => $blockTypes,
				'insert' => $insert,
			]) ?>
		</div>
	</details>
<?php endif ?>
