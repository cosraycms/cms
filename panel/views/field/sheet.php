<?php

declare(strict_types=1);

$fields = (array) u($fields);
$fieldsets = (array) u($fieldsets);
$content = (array) u($content);
$locales = (array) u($locales);
$defaultLocale = (string) $defaultLocale;
$uid = (string) $uid;
$assets = (array) u($assets ?? []);
$pathSourceFields = (array) u($pathSourceFields ?? []);

$fieldsByName = [];

foreach ($fields as $field) {
	if (!is_array($field) || !is_string($field['name'] ?? null)) {
		continue;
	}

	$fieldsByName[$field['name']] = $field;
}

$fieldsetsByFirstField = [];
$fieldsetMembers = [];

foreach ($fieldsets as $fieldset) {
	if (!is_array($fieldset)) {
		continue;
	}

	$members = array_values(array_filter(
		(array) ($fieldset['fields'] ?? []),
		static fn(mixed $name): bool => is_string($name),
	));

	if ($members === []) {
		continue;
	}

	$fieldsetsByFirstField[$members[0]] = $fieldset;

	foreach ($members as $member) {
		$fieldsetMembers[$member] = true;
	}
}

// The pane renders a stack of sections: each fieldset is one, and every run
// of fields between fieldsets forms an anonymous one, so dividers can sit
// between sections without wrapping single fields.
$sections = [];
$run = null;

foreach ($fields as $field) {
	if (!is_array($field) || ($field['hidden'] ?? false)) {
		continue;
	}

	$fieldName = (string) ($field['name'] ?? '');

	if (isset($fieldsetsByFirstField[$fieldName])) {
		$sections[] = ['fieldset' => $fieldsetsByFirstField[$fieldName]];
		$run = null;
	} elseif (!isset($fieldsetMembers[$fieldName])) {
		if ($run === null) {
			$sections[] = ['fields' => []];
			$run = array_key_last($sections);
		}

		$sections[$run]['fields'][] = $field;
	}
}
?>

<div class="sheet">
	<?php foreach ($sections as $section): ?>
		<?php if (isset($section['fieldset'])): ?>
			<?php $this->include('field/fieldset', [
				'fieldset' => $section['fieldset'],
				'fieldsByName' => $fieldsByName,
				'content' => $content,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
				'uid' => $uid,
				'assets' => $assets,
				'pathSourceFields' => $pathSourceFields,
			]) ?>
		<?php else: ?>
			<div class="cms-fields">
				<?php foreach ($section['fields'] as $field): ?>
					<?php $this->include('field/item', [
						'field' => $field,
						'content' => $content,
						'locales' => $locales,
						'defaultLocale' => $defaultLocale,
						'uid' => $uid,
						'assets' => $assets,
						'pathSourceFields' => $pathSourceFields,
					]) ?>
				<?php endforeach ?>
			</div>
		<?php endif ?>
	<?php endforeach ?>
</div>
