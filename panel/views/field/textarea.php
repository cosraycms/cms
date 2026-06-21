<?php

$rowsValue = $this->unwrap($properties['rows'] ?? null);
$rows = is_int($rowsValue) ? $rowsValue : 5;
?>
<?php $this->insert('field/locale-rows', get_defined_vars(), slot: function (
	array $row,
) use ($required, $rows): void { ?>
	<textarea
		class="field-input"
		id="<?= $this->escape($row['inputId']) ?>"
		name="<?= $this->escape($row['inputName']) ?>"
		rows="<?= $rows ?>"
		<?php if ($required): ?>
			required
		<?php endif ?>
	><?= $this->escape((string) ($row['value'] ?? '')) ?></textarea>
<?php });
