<?php

$fieldName = (string) $this->unwrap($name);
$fieldType = (string) $this->unwrap($type);
$currentValue = $this->unwrap($value[\Cosray\Field\Field::NEUTRAL_LOCALE] ?? '');
?>
<input type="hidden" name="<?= $this->escape(
	'content[' . $fieldName . '][type]',
) ?>" value="<?= $this->escape($fieldType) ?>">
<input
	class="field-input"
	type="number"
	step="any"
	id="<?= $inputId ?>"
	name="<?= $this->escape(
	'content[' . $fieldName . '][value][' . \Cosray\Field\Field::NEUTRAL_LOCALE . ']',
) ?>"
	value="<?= $this->escape((string) ($currentValue ?? '')) ?>"
	<?php if ($required): ?>
		required
	<?php endif ?>
>
