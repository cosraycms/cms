<?php $this->insert('field/locale-rows', get_defined_vars(), slot: function (
	array $row,
) use ($required): void { ?>
	<input
		class="field-input"
		type="url"
		id="<?= $this->escape($row['inputId']) ?>"
		name="<?= $this->escape($row['inputName']) ?>"
		value="<?= $this->escape((string) ($row['value'] ?? '')) ?>"
		<?php if ($required): ?>
			required
		<?php endif ?>
	>
<?php });
