<?php

$fieldName = (string) $this->unwrap($name);
$fieldType = (string) $this->unwrap($type);
$baseInputId = (string) $this->unwrap($inputId);
$valueMap = $this->unwrap($value ?? null);
$valueMap = is_array($valueMap) ? $valueMap : [\Cosray\Field\Field::NEUTRAL_LOCALE => $valueMap];
$multiple = count($valueMap) > 1;
?>
<input type="hidden" name="<?= $this->escape(
	'content[' . $fieldName . '][type]',
) ?>" value="<?= $this->escape($fieldType) ?>">
<?php $index = 0 ?>
<?php foreach ($valueMap as $locale => $currentValue): ?>
	<?php

	$locale = (string) $locale;
	$currentInputId = $index === 0 ? $baseInputId : $baseInputId . '-' . $locale;
	$currentInputName = 'content[' . $fieldName . '][value][' . $locale . ']';
	?>
	<div class="field-input-row">
		<?php if ($multiple): ?>
			<span class="field-locale"><?= $locale ?></span>
		<?php endif ?>
		<?php $this->slot([
			'inputId' => $currentInputId,
			'inputName' => $currentInputName,
			'value' => $currentValue,
			'locale' => $locale,
		]) ?>
	</div>
	<?php $index++ ?>
<?php endforeach ?>
