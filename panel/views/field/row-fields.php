<?php

use function Cosray\escape;

// The fields of one typed row — an entry or a block — rendered through
// the regular field wrapper at {$rowName}[fields][{sub}] and grouped by
// the row type's fieldsets. Shared by the entries and blocks views.
// Receives: type (the row type descriptor), fieldsData, rowName, rowId,
// locales, defaultLocale, node, assets.

$type = (array) $this->unwrap($type);
$fieldsData = (array) ($this->unwrap($fieldsData ?? null) ?? []);
$rowName = (string) $rowName;
$rowId = (string) $rowId;
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$node = (string) ($node ?? '');
$assets = (array) ($this->unwrap($assets ?? null) ?? []);

$span = static function (mixed $width): string {
	$width = is_int($width) && $width > 0 && $width <= 100 ? $width : 100;

	return "grid-column: span {$width} / span {$width}";
};

$renderField = function (array $sub) use (
	$span,
	$fieldsData,
	$rowName,
	$rowId,
	$locales,
	$defaultLocale,
	$node,
	$assets,
): void {
	$subName = (string) ($sub['name'] ?? '');
	// Conditions are top-level-only; emitting them here would evaluate
	// against a same-named top-level field. Scoped conditions come later.
	unset($sub['when']);
	?>
	<div style="<?= $span($sub['width'] ?? null) ?>">
		<?php $this->insert('field/field', [
			'field' => $sub,
			'data' => $fieldsData[$subName] ?? null,
			'locales' => $locales,
			'defaultLocale' => $defaultLocale,
			'node' => $node,
			'assets' => $assets,
			'nameRoot' => "{$rowName}[fields][{$subName}]",
			'idRoot' => "{$rowId}-{$subName}",
		]) ?>
	</div>
	<?php
};

$subs = array_values(array_filter(
	(array) ($type['fields'] ?? []),
	static fn(mixed $sub): bool => is_array($sub) && !($sub['hidden'] ?? false),
));

$fieldsetsByFirst = [];
$fieldsetMembers = [];

foreach ((array) ($type['fieldsets'] ?? []) as $fieldset) {
	$members = array_values(array_filter(
		(array) ($fieldset['fields'] ?? []),
		static fn(mixed $member): bool => is_string($member),
	));

	if ($members === []) {
		continue;
	}

	$fieldsetsByFirst[$members[0]] = ['fieldset' => $fieldset, 'members' => $members];

	foreach ($members as $member) {
		$fieldsetMembers[$member] = true;
	}
}

$subsByName = [];

foreach ($subs as $sub) {
	$subsByName[(string) ($sub['name'] ?? '')] = $sub;
}
?>
<?php foreach ($subs as $sub): ?>
	<?php $subName = (string) ($sub['name'] ?? ''); ?>
	<?php if (isset($fieldsetsByFirst[$subName])): ?>
		<?php $fieldset = $fieldsetsByFirst[$subName]['fieldset']; ?>
		<fieldset class="cms-fieldset" style="<?= $span($fieldset['width'] ?? null) ?>">
			<?php if (is_string($fieldset['label'] ?? null) && $fieldset['label'] !== ''): ?>
				<legend class="legend"><?= escape($fieldset['label']) ?></legend>
			<?php endif ?>
			<?php if (is_string($fieldset['description'] ?? null) && $fieldset['description'] !== ''): ?>
				<div class="description"><?= escape($fieldset['description']) ?></div>
			<?php endif ?>
			<div class="cms-fields fields">
				<?php foreach ($fieldsetsByFirst[$subName]['members'] as $member): ?>
					<?php if (isset($subsByName[$member])) {
						$renderField($subsByName[$member]);
					} ?>
				<?php endforeach ?>
			</div>
		</fieldset>
	<?php elseif (!isset($fieldsetMembers[$subName])): ?>
		<?php $renderField($sub) ?>
	<?php endif ?>
<?php endforeach ?>
