<?php

declare(strict_types=1);

$field = (array) $this->unwrap($field);
$content = (array) $this->unwrap($content);
$locales = (array) $this->unwrap($locales);
$assets = (array) $this->unwrap($assets);
$pathSourceFields = (array) $this->unwrap($pathSourceFields);
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

$style = "grid-column: span {$width} / span {$width}; grid-row: span {$rowspan} / span {$rowspan}";
?>

<div<?= $isPathSource ? ' class="js-path-source"' : '' ?> style="<?= $this->escape($style) ?>">
	<?php $this->insert('field/field', [
		'field' => $field,
		'data' => $content[$fieldName] ?? null,
		'locales' => $locales,
		'defaultLocale' => $defaultLocale,
		'node' => $uid,
		'assets' => $assets,
		'globalLocales' => $globalLocales ?? false,
	]) ?>
</div>
