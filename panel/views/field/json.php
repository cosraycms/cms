<?php

$help = $this->unwrap($jsonHelp
?? 'Edit this field as JSON until the richer panel control is ported.');
?>
<p class="field-description"><?= $this->escape((string) $help) ?></p>
<?php $this->insert('field/locale-rows', get_defined_vars(), slot: function (
	array $row,
) use ($required): void {
	$json = json_encode(
		$row['value'] ?? [],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
	);
	$json = is_string($json) ? $json : '[]';
	?>
	<textarea
		class="field-input field-json"
		id="<?= $this->escape($row['inputId']) ?>"
		name="<?= $this->escape($row['inputName']) ?>"
		rows="10"
		<?php if ($required): ?>
			required
		<?php endif ?>
	><?= $this->escape($json) ?></textarea>
<?php
});
