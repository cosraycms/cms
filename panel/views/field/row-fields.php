<?php

// The fields of one typed row — an entry or a block — rendered through
// the regular field wrapper at {$rowName}[fields][{sub}] and grouped by
// the row type's fieldsets. Shared by the entries and blocks views.
// Receives: type (the row type descriptor), fieldsData, rowName, rowId,
// locales, defaultLocale, node, assets.

$type = (array) $this->unwrap($type);

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
					<?php if (isset($subsByName[$member])): ?>
						<?php $this->insert('field/row-fields/field', ['sub' => $subsByName[$member]]) ?>
					<?php endif ?>
				<?php endforeach ?>
			</div>
		</fieldset>
	<?php elseif (!isset($fieldsetMembers[$subName])): ?>
		<?php $this->insert('field/row-fields/field', ['sub' => $sub]) ?>
	<?php endif ?>
<?php endforeach ?>
