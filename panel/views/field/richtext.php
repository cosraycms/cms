<?php

$componentAssets = $this->unwrap($componentAssets ?? []);
$modulePath = is_array($componentAssets) && is_string($componentAssets['richtext'] ?? null)
	? $componentAssets['richtext']
	: ($this->unwrap($panelPath ?? null) ?: '/cp') . '/assets/app/components/richtext.js';
?>
<?php $this->insert('field/locale-rows', get_defined_vars(), slot: function (
	array $row,
) use ($modulePath): void { ?>
	<textarea
		id="<?= $this->escape($row['inputId']) ?>"
		name="<?= $this->escape($row['inputName']) ?>"
		hidden
	><?= $this->escape((string) ($row['value'] ?? '')) ?></textarea>
	<cosray-richtext-editor
		data-module="<?= $this->escape((string) $modulePath) ?>"
		data-value-input="<?= $this->escape($row['inputId']) ?>"
	></cosray-richtext-editor>
<?php });
