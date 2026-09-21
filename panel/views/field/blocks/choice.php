<?php

$choice = (array) $this->unwrap($choice);
$insert = (string) $this->unwrap($insert);
?>
<button type="button" class="choice"
	data-repeater-add="<?= $this->escape($choice['type']) ?>"
	data-repeater-insert="<?= $this->escape($insert) ?>">
	<span class="icon" aria-hidden="true"><?= $choice['icon'] ?></span>
	<span><?= $this->escape($choice['label']) ?></span>
</button>
