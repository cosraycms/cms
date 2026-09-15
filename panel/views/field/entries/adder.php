<?php

$entryType = (array) $this->unwrap($entryType);
$empty = (bool) $empty;
$full = (bool) $full;
$single = (bool) $single;

$label = $single
	? ($empty ? __('field:add-first-entry') : __('field:add-entry'))
	: __('field:add-typed', ['label' => (string) ($entryType['label'] ?? __('field:entry'))]);
?>
<button
	type="button"
	class="adder"
	data-repeater-add="<?= $this->escape((string) $entryType['type']) ?>"
	<?= $full ? 'hidden' : '' ?>>
	<?= \Cosray\Panel\Icon::render('plus-circle') ?>
	<?= $this->escape($label) ?>
</button>
