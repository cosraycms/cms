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
$rows = is_int($field['rows'] ?? null) ? $field['rows'] : 1;

if ($width > 100 || $width <= 0) {
	$width = 100;
}

if ($rows > 100 || $rows <= 0) {
	$rows = 100;
}

$style = "grid-column: span {$width} / span {$width}; grid-row: span {$rows} / span {$rows}";
?>

<div<?= $isPathSource ? ' class="js-path-source"' : '' ?> style="<?= $this->escape($style) ?>">
	<?php $this->insert('field/field', [
		'field' => $field,
		'data' => $content[$fieldName] ?? null,
		'locales' => $locales,
		'defaultLocale' => $defaultLocale,
		'node' => $uid,
		'assets' => $assets,
	]) ?>
</div>
