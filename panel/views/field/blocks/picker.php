<?php

$commonChoices = (array) $this->unwrap($commonChoices);
$insert = (string) $this->unwrap($insert);
$more = (bool) $this->unwrap($more);
?>
<?php foreach ($commonChoices as $choice) {
	$this->insert('field/blocks/choice', ['choice' => $choice, 'insert' => $insert]);
} ?>
<?php if ($more): ?>
	<hr />
	<button type="button" data-block-catalog-open data-repeater-insert="<?= $this->escape($insert) ?>">
		<?= \Cosray\Panel\Icon::render('collection') ?>
		<?= $this->escape(__('field:more-blocks')) ?>
	</button>
<?php endif ?>
