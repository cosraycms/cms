<?php

declare(strict_types=1);

$field = (array) $this->unwrap($field);
$content = (array) $this->unwrap($content);
$locales = (array) $this->unwrap($locales);
$assets = (array) $this->unwrap($assets);
$pathSourceFields = (array) $this->unwrap($pathSourceFields);
$fieldName = (string) ($field['name'] ?? '');
$isPathSource = in_array($fieldName, $pathSourceFields, true);
?>

<div<?= $isPathSource ? ' class="js-path-source"' : '' ?> style="
	grid-column: <?= $span($field['width'] ?? null, 100) ?>;
	grid-row: <?= $span($field['rows'] ?? null, 1) ?>">
	<?php $this->insert('field/field', [
		'field' => $field,
		'data' => $content[$fieldName] ?? null,
		'locales' => $locales,
		'defaultLocale' => $defaultLocale,
		'node' => $uid,
		'assets' => $assets,
	]) ?>
</div>
