<?php

declare(strict_types=1);

use function Cosray\escape;

$fieldset = (array) u($fieldset);
$fieldsByName = (array) u($fieldsByName);
$content = (array) u($content);
$locales = (array) u($locales);
$assets = (array) u($assets);
$pathSourceFields = (array) u($pathSourceFields);
$name = (string) ($fieldset['name'] ?? '');
$label = $fieldset['label'] ?? null;
$description = $fieldset['description'] ?? null;
$descriptionId = "fieldset-{$name}-description";
?>

<fieldset
	class="cms-fieldset"
	data-fieldset="<?= escape($name) ?>"
	<?= is_string($description) && $description !== ''
		? 'aria-describedby="' . escape($descriptionId) . '"'
		: '' ?>>
	<?php if (is_string($label) && $label !== ''): ?>
		<legend class="legend"><?= escape($label) ?></legend>
	<?php endif ?>
	<?php if (is_string($description) && $description !== ''): ?>
		<div id="<?= escape($descriptionId) ?>" class="description">
			<?= escape($description) ?>
		</div>
	<?php endif ?>
	<div class="cms-fields fields">
		<?php foreach ((array) ($fieldset['fields'] ?? []) as $fieldName): ?>
			<?php if (!is_string($fieldName) || !isset($fieldsByName[$fieldName])) {
				continue;
			} ?>
			<?php $this->include('field/item', [
				'field' => $fieldsByName[$fieldName],
				'content' => $content,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
				'uid' => $uid,
				'assets' => $assets,
				'pathSourceFields' => $pathSourceFields,
			]) ?>
		<?php endforeach ?>
	</div>
</fieldset>
