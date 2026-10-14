<?php

$commonChoices = (array) u($commonChoices);
$insert = (string) u($insert);
$more = (bool) u($more);
?>
<?php foreach ($commonChoices as $choice) {
	$this->include('field/blocks/choice', ['choice' => $choice, 'insert' => $insert]);
} ?>
<?php if ($more): ?>
	<hr />
	<button type="button" data-block-catalog-open data-repeater-insert="<?= $this->escape($insert) ?>">
		<?= \Cosray\Panel\Icon::render('collection') ?>
		<?= $this->escape(__('field:more-blocks')) ?>
	</button>
<?php endif ?>
