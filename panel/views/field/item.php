<?php

declare(strict_types=1);

$field = (array) u($field);
$content = (array) u($content);
$locales = (array) u($locales);
$assets = (array) u($assets);
$pathSourceFields = (array) u($pathSourceFields);
$fieldName = (string) ($field['name'] ?? '');
$isPathSource = in_array($fieldName, $pathSourceFields, true);
$width = is_int($field['width'] ?? null) ? $field['width'] : 100;
$rowspan = is_int($field['rowspan'] ?? null) ? $field['rowspan'] : 1;

if ($width > 100 || $width <= 0) {
	$width = 100;
}

if ($rowspan > 100 || $rowspan <= 0) {
	$rowspan = 100;
}

$gridStyle = "grid-column: span {$width} / span {$width}; --rows: {$rowspan}";
?>

<?php $this->include('field/field', [
	'field' => $field,
	'data' => $content[$fieldName] ?? null,
	'locales' => $locales,
	'defaultLocale' => $defaultLocale,
	'node' => $uid,
	'assets' => $assets,
	'gridStyle' => $gridStyle,
	'pathSource' => $isPathSource,
]) ?>
