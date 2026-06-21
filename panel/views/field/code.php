<?php

$fieldName = (string) $this->unwrap($name);
$metaMap = $this->unwrap($meta ?? []);
$syntax = is_array($metaMap)
	? (string) ($metaMap['syntax'][\Cosray\Field\Field::NEUTRAL_LOCALE] ?? 'plaintext')
	: 'plaintext';
$componentAssets = $this->unwrap($componentAssets ?? []);
$modulePath = is_array($componentAssets) && is_string($componentAssets['code'] ?? null)
	? $componentAssets['code']
	: ($this->unwrap($panelPath ?? null) ?: '/cp') . '/assets/app/components/code.js';
?>
<input type="hidden" name="<?= $this->escape(
	'content[' . $fieldName . '][meta][syntax][' . \Cosray\Field\Field::NEUTRAL_LOCALE . ']',
) ?>" value="<?= $this->escape($syntax) ?>">
<?php $this->insert('field/locale-rows', get_defined_vars(), slot: function (
	array $row,
) use ($syntax, $modulePath): void { ?>
	<textarea
		id="<?= $this->escape($row['inputId']) ?>"
		name="<?= $this->escape($row['inputName']) ?>"
		hidden
	><?= $this->escape((string) ($row['value'] ?? '')) ?></textarea>
	<cosray-code-editor
		data-module="<?= $this->escape((string) $modulePath) ?>"
		data-value-input="<?= $this->escape($row['inputId']) ?>"
		syntax="<?= $this->escape($syntax) ?>"
	></cosray-code-editor>
<?php });
