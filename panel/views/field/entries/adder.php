<?php

$entryType = (array) $this->unwrap($entryType);
$empty = (bool) $empty;
$full = (bool) $full;
$single = (bool) $single;

$label = $single
	? __($empty ? 'field:add-first-entry' : 'field:add-entry')
	: __('field:add-typed', ['label' => (string) ($entryType['label'] ?? __('field:entry'))]);
?>
<button
	type="button"
	class="adder"
	data-repeater-add="<?= $this->escape((string) $entryType['type']) ?>"
	<?= $full ? 'hidden' : '' ?>>
	<?php $this->insert('icon/plus.svg') ?>
	<?= $this->escape($label) ?>
</button>
