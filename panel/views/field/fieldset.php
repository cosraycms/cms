<?php

declare(strict_types=1);

use function Cosray\escape;

$fieldset = (array) $this->unwrap($fieldset);
$fieldsByName = (array) $this->unwrap($fieldsByName);
$content = (array) $this->unwrap($content);
$locales = (array) $this->unwrap($locales);
$assets = (array) $this->unwrap($assets);
$pathSourceFields = (array) $this->unwrap($pathSourceFields);
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
		: '' ?>
	style="grid-column: <?= $span($fieldset['width'] ?? null, 100) ?>">
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
			<?php $this->insert('field/item', [
				'field' => $fieldsByName[$fieldName],
				'content' => $content,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
				'uid' => $uid,
				'assets' => $assets,
				'pathSourceFields' => $pathSourceFields,
				'span' => $span,
			]) ?>
		<?php endforeach ?>
	</div>
</fieldset>
