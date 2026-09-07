<?php

// The + that puts a new block before the row it sits in. One offered
// type inserts at once; several open the type picker.

$blockTypes = (array) $this->unwrap($blockTypes);
$single = $this->unwrap($single ?? null);
$single = is_string($single) ? $single : null;
$insert = (string) $this->unwrap($insert);
$id = (string) $this->unwrap($id);
$label = (string) $this->unwrap($label);
?>
<?php if ($single !== null): ?>
	<button
		type="button"
		class="inserter"
		data-repeater-add="<?= $this->escape($single) ?>"
		data-repeater-insert="<?= $this->escape($insert) ?>"
		aria-label="<?= $this->escape($label) ?>"
		title="<?= $this->escape($label) ?>"><?= \Cosray\Panel\Icon::render('plus') ?></button>
<?php else: ?>
	<button type="button" class="inserter" popovertarget="<?= $this->escape($id) ?>"
		aria-haspopup="menu" aria-label="<?= $this->escape($label) ?>" title="<?= $this->escape($label) ?>">
		<?= \Cosray\Panel\Icon::render('plus') ?>
	</button>
	<div id="<?= $this->escape($id) ?>" class="cms-action-menu" popover="auto" data-action-menu>
			<?php $this->insert('field/blocks/picker', [
				'blockTypes' => $blockTypes,
				'insert' => $insert,
			]) ?>
	</div>
<?php endif ?>
