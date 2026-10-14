<?php

$sub = (array) u($sub);

// A read-only row hands its own state to every sub-field: the field it
// belongs to is ignored whole on save.
if ((bool) (u($readonly ?? null) ?? false)) {
	$sub['immutable'] = true;
}

$fieldsData = (array) (u($fieldsData ?? null) ?? []);
$rowName = (string) u($rowName);
$rowId = (string) u($rowId);
$subName = (string) ($sub['name'] ?? '');
$width = is_int($sub['width'] ?? null) ? $sub['width'] : 100;
$width = $width > 0 && $width <= 100 ? $width : 100;
$gridStyle = "grid-column: span {$width} / span {$width}";

// Conditions are top-level-only; emitting them here would evaluate
// against a same-named top-level field. Scoped conditions come later.
unset($sub['when']);
?>
<?php $this->include('field/field', [
	'field' => $sub,
	'bareLabel' => !u($labels ?? true),
	'data' => $fieldsData[$subName] ?? null,
	'nameRoot' => "{$rowName}[fields][{$subName}]",
	'idRoot' => "{$rowId}-{$subName}",
	'gridStyle' => $gridStyle,
	'pathSource' => false,
]) ?>
