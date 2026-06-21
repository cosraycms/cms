<?php

$baseInputId = (string) $this->unwrap($inputId);
$describedBy = [];
$descriptionText = $this->unwrap($description ?? null);

if (is_string($descriptionText) && trim($descriptionText) !== '') {
	$describedBy[] = $baseInputId . '-description';
}

$errorList = $this->unwrap($errors ?? []);

if (is_array($errorList) && $errorList !== []) {
	$describedBy[] = $baseInputId . '-errors';
}

$ariaDescribedBy = implode(' ', $describedBy);
?>
<?php $this->insert('field/locale-rows', get_defined_vars(), slot: function (
	array $row,
) use ($required, $ariaDescribedBy): void { ?>
	<input
		class="field-input"
		type="text"
		id="<?= $this->escape($row['inputId']) ?>"
		name="<?= $this->escape($row['inputName']) ?>"
		value="<?= $this->escape((string) ($row['value'] ?? '')) ?>"
		<?php if ($required): ?>
			required
		<?php endif ?>
		<?php if ($ariaDescribedBy !== ''): ?>
			aria-describedby="<?= $this->escape($ariaDescribedBy) ?>"
		<?php endif ?>
	>
<?php });
