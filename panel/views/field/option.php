<?php

$fieldName = (string) $this->unwrap($name);
$fieldType = (string) $this->unwrap($type);
$currentValue = (string) $this->unwrap($value[\Cosray\Field\Field::NEUTRAL_LOCALE] ?? '');
$options = $this->unwrap($properties['options'] ?? []);
?>
<input type="hidden" name="<?= $this->escape(
	'content[' . $fieldName . '][type]',
) ?>" value="<?= $this->escape($fieldType) ?>">
<select
	class="field-input"
	id="<?= $inputId ?>"
	name="<?= $this->escape(
	'content[' . $fieldName . '][value][' . \Cosray\Field\Field::NEUTRAL_LOCALE . ']',
) ?>"
	<?php if ($required): ?>
		required
	<?php endif ?>
>
	<?php foreach (is_array($options) ? $options : [] as $option): ?>
		<?php

		$optionValue = is_array($option) ? (string) ($option['value'] ?? '') : (string) $option;
		$optionLabel = is_array($option) ? (string) ($option['label'] ?? $optionValue) : $optionValue;
		?>
		<option value="<?= $this->escape($optionValue) ?>" <?php if (
			$optionValue === $currentValue
		): ?>selected<?php endif ?>><?= $this->escape($optionLabel) ?></option>
	<?php endforeach ?>
</select>
