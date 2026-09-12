<?php

$sub = (array) $this->unwrap($sub);

// A read-only row hands its own state to every sub-field: the field it
// belongs to is ignored whole on save.
if ((bool) ($this->unwrap($readonly ?? null) ?? false)) {
	$sub['immutable'] = true;
}

$fieldsData = (array) ($this->unwrap($fieldsData ?? null) ?? []);
$rowName = (string) $this->unwrap($rowName);
$rowId = (string) $this->unwrap($rowId);
$subName = (string) ($sub['name'] ?? '');
$width = is_int($sub['width'] ?? null) ? $sub['width'] : 100;
$width = $width > 0 && $width <= 100 ? $width : 100;
$style = "grid-column: span {$width} / span {$width}";

// Conditions are top-level-only; emitting them here would evaluate
// against a same-named top-level field. Scoped conditions come later.
unset($sub['when']);
?>
<div style="<?= $this->escape($style) ?>">
	<?php $this->insert('field/field', [
		'field' => $sub,
		'ownLocales' => !$this->unwrap($ownsLocales ?? false),
		'bareLabel' => !$this->unwrap($labels ?? true),
		'data' => $fieldsData[$subName] ?? null,
		'nameRoot' => "{$rowName}[fields][{$subName}]",
		'idRoot' => "{$rowId}-{$subName}",
		'globalLocales' => $globalLocales ?? false,
	]) ?>
</div>
