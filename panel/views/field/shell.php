<?php

$classes = ['field'];
$fieldView = (string) $this->unwrap($fieldView ?? 'field/unsupported');

if ($required) {
	$classes[] = 'is-required';
}

if ($hidden) {
	$classes[] = 'is-hidden';
}
?>
<div class="<?= implode(
	' ',
	$classes,
) ?>" data-field-name="<?= $name ?>" data-field-type="<?= $type ?>">
	<?php if (!$hidden): ?>
		<?php $this->insert('field/label') ?>
		<?php $this->insert('field/description') ?>
	<?php endif ?>

	<div class="field-control">
		<?php $this->insert($fieldView) ?>
	</div>

	<?php $this->insert('field/errors') ?>
</div>
