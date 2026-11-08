<?php

// The fields of one typed row — an entry or a block — rendered through
// the regular field wrapper at {$rowName}[fields][{sub}] and grouped by
// the row type's fieldsets. Shared by the entries and blocks views.
// Receives: type (the row type descriptor), fieldsData, rowName, rowId,
// labels (whether the sub-fields show their own), locales, defaultLocale,
// node, assets, and optionally subs (the sub-fields to show, for a block
// that edits some in its dialog). A fieldset shows the members it is given.

$type = (array) u($type);
$given = u($subs ?? null);

$subs = array_values(array_filter(
	is_array($given) ? $given : (array) ($type['fields'] ?? []),
	static fn(mixed $sub): bool => is_array($sub) && !($sub['hidden'] ?? false),
));

$subsByName = [];

foreach ($subs as $sub) {
	$subsByName[(string) ($sub['name'] ?? '')] = $sub;
}

$fieldsetsByFirst = [];
$fieldsetMembers = [];

foreach ((array) ($type['fieldsets'] ?? []) as $fieldset) {
	$members = array_values(array_filter(
		(array) ($fieldset['fields'] ?? []),
		static fn(mixed $member): bool => is_string($member) && isset($subsByName[$member]),
	));

	if ($members === []) {
		continue;
	}

	$fieldsetsByFirst[$members[0]] = ['fieldset' => $fieldset, 'members' => $members];

	foreach ($members as $member) {
		$fieldsetMembers[$member] = true;
	}
}
?>
<?php foreach ($subs as $sub): ?>
	<?php $subName = (string) ($sub['name'] ?? ''); ?>
	<?php if (isset($fieldsetsByFirst[$subName])): ?>
		<?php

		$fieldset = $fieldsetsByFirst[$subName]['fieldset'];
		$width = is_int($fieldset['width'] ?? null) ? $fieldset['width'] : 100;
		$width = $width > 0 && $width <= 100 ? $width : 100;
		$style = "grid-column: span {$width} / span {$width}";
		?>
		<fieldset class="cms-fieldset" style="<?= $this->escape($style) ?>">
			<?php if (is_string($fieldset['label'] ?? null) && $fieldset['label'] !== ''): ?>
				<legend class="legend"><?= $this->escape($fieldset['label']) ?></legend>
			<?php endif ?>
			<?php if (is_string($fieldset['description'] ?? null) && $fieldset['description'] !== ''): ?>
				<div class="description"><?= $this->escape($fieldset['description']) ?></div>
			<?php endif ?>
			<div class="cms-fields fields">
				<?php foreach ($fieldsetsByFirst[$subName]['members'] as $member): ?>
					<?php $this->include('field/row-fields/field', ['sub' => $subsByName[$member]]) ?>
				<?php endforeach ?>
			</div>
		</fieldset>
	<?php elseif (!isset($fieldsetMembers[$subName])): ?>
		<?php $this->include('field/row-fields/field', ['sub' => $sub]) ?>
	<?php endif ?>
<?php endforeach ?>
