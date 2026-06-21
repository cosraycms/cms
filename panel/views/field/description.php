<?php $descriptionText = $this->unwrap($description ?? null) ?>
<?php if (is_string($descriptionText) && trim($descriptionText) !== ''): ?>
	<p class="field-description" id="<?= $inputId ?>-description"><?= $this->escape(
	$descriptionText,
) ?></p>
<?php endif ?>
