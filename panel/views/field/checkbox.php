<?php

$fieldName = (string) $this->unwrap($name);
$fieldType = (string) $this->unwrap($type);
$currentValue = $this->unwrap($value[\Cosray\Field\Field::NEUTRAL_LOCALE] ?? false) === true;
$valueName = 'content[' . $fieldName . '][value][' . \Cosray\Field\Field::NEUTRAL_LOCALE . ']';
?>
<input type="hidden" name="<?= $this->escape(
	'content[' . $fieldName . '][type]',
) ?>" value="<?= $this->escape($fieldType) ?>">
<input type="hidden" name="<?= $this->escape($valueName) ?>" value="0">
<input
	class="field-checkbox"
	type="checkbox"
	id="<?= $inputId ?>"
	name="<?= $this->escape($valueName) ?>"
	value="1"
	<?php if ($currentValue): ?>
		checked
	<?php endif ?>
>
