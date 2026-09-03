<?php

$blockTypes = (array) $this->unwrap($blockTypes);
$insert = (string) $this->unwrap($insert);
?>
<?php foreach ($blockTypes as $blockType): ?>
	<button
		type="button"
		data-repeater-add="<?= $this->escape((string) $blockType['type']) ?>"
		data-repeater-insert="<?= $this->escape($insert) ?>">
		<?= $this->escape((string) ($blockType['label'] ?? __('field:block'))) ?>
	</button>
<?php endforeach ?>
