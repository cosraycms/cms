<?php

$choice = (array) $this->unwrap($choice);
$insert = $this->unwrap($insert ?? null);
?>
<button type="button" class="choice"
	<?php if ($insert === null): ?>
		data-block-choice="<?= $this->escape($choice['type']) ?>"
		data-handle="<?= $this->escape($choice['handle']) ?>"
		tabindex="-1"
	<?php else: ?>
		data-repeater-add="<?= $this->escape($choice['type']) ?>"
		data-repeater-insert="<?= $this->escape($insert) ?>"
	<?php endif ?>>
	<span class="icon" aria-hidden="true"><?= $choice['icon'] ?></span>
	<span><?= $this->escape($choice['label']) ?></span>
</button>
