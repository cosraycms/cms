<?php

use function Cosray\escape;

// Server-rendered entries: a typed repeater whose rows are groups of
// regular field wrappers. Add/remove/move/renumber is wired by the
// repeater behavior; one inert template per allowed entry type stamps
// fresh rows entirely client-side. Receives the neutral-locale row list
// in $value and the renumber base (content[f][value][zxx]) in $name.

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$value = $this->unwrap($value ?? null);
$rows = is_array($value) ? array_values($value) : [];
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$node = (string) ($node ?? '');
$assets = (array) ($this->unwrap($assets ?? null) ?? []);
$max = $props['max'] ?? null;

$entryTypes = [];

foreach ((array) ($props['entryTypes'] ?? []) as $entryType) {
	if (is_array($entryType) && is_string($entryType['type'] ?? null)) {
		$entryTypes[$entryType['type']] = $entryType;
	}
}

$title = static function (array $entryType, array $fieldsData): string {
	foreach ((array) ($entryType['fields'] ?? []) as $sub) {
		$subName = $sub['name'] ?? null;
		$subValue = is_string($subName) ? $fieldsData[$subName]['value'] ?? null : null;

		if (!is_array($subValue)) {
			continue;
		}

		foreach ($subValue as $localized) {
			if (is_string($localized) && trim($localized) !== '') {
				return mb_strlen($localized) > 50 ? mb_substr($localized, 0, 50) . '…' : $localized;
			}
		}
	}

	return (string) ($entryType['label'] ?? __('field:entry'));
};

$span = static function (mixed $width): string {
	$width = is_int($width) && $width > 0 && $width <= 100 ? $width : 100;

	return "grid-column: span {$width} / span {$width}";
};

$renderField = function (array $sub, array $fieldsData, string $rowName, string $rowId) use (
	$span,
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

$row = function (int|string $index, ?array $rowData, array $entryType) use (
	$name,
	$id,
	$title,
	$span,
	$renderField,
): void {
	$rowName = "{$name}[{$index}]";
	$rowId = "{$id}-{$index}";
	$uid = is_string($rowData['uid'] ?? null) ? $rowData['uid'] : '';
	$fieldsData = is_array($rowData['fields'] ?? null) ? $rowData['fields'] : [];
	$subs = array_values(array_filter(
		(array) ($entryType['fields'] ?? []),
		static fn(mixed $sub): bool => is_array($sub) && !($sub['hidden'] ?? false),
	));

	$fieldsetsByFirst = [];
	$fieldsetMembers = [];

	foreach ((array) ($entryType['fieldsets'] ?? []) as $fieldset) {
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
	<div class="cms-entry" data-repeater-row>
		<div class="head">
			<button type="button" class="title" data-repeater-collapse aria-expanded="true">
				<span class="number" data-repeater-label>
					<?= is_int($index) ? $index + 1 . '.' : '' ?>
				</span>
				<span class="text">
					<?= escape($rowData === null
						? (string) ($entryType['label'] ?? __('field:entry'))
						: $title($entryType, $fieldsData)) ?>
				</span>
			</button>
			<span class="controls">
				<button
					type="button"
					class="cms-button"
					data-repeater-move="up"
					aria-label="<?= escape(__('common:move-up')) ?>">↑</button>
				<button
					type="button"
					class="cms-button"
					data-repeater-move="down"
					aria-label="<?= escape(__('common:move-down')) ?>">↓</button>
				<button
					type="button"
					class="cms-button"
					data-repeater-remove
					aria-label="<?= escape(__('field:remove-entry')) ?>">×</button>
			</span>
		</div>
		<input
			type="hidden"
			data-repeater-uid
			name="<?= escape("{$rowName}[uid]") ?>"
			value="<?= escape($uid) ?>" />
		<input
			type="hidden"
			name="<?= escape("{$rowName}[type]") ?>"
			value="<?= escape((string) $entryType['type']) ?>" />
		<div class="body cms-fields" data-repeater-body>
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
									$renderField($subsByName[$member], $fieldsData, $rowName, $rowId);
								} ?>
							<?php endforeach ?>
						</div>
					</fieldset>
				<?php elseif (!isset($fieldsetMembers[$subName])): ?>
					<?php $renderField($sub, $fieldsData, $rowName, $rowId) ?>
				<?php endif ?>
			<?php endforeach ?>
		</div>
	</div>
	<?php
};

$addLabel = static function (array $entryType, bool $empty) use ($entryTypes): string {
	if (count($entryTypes) === 1) {
		return $empty ? __('field:add-first-entry') : __('field:add-entry');
	}

	return __('field:add-typed', ['label' => (string) ($entryType['label'] ?? __('field:entry'))]);
};

$full = is_int($max) && $max > 0 && count($rows) >= $max;
?>
<div
	class="cms-entries"
	data-repeater
	data-name="<?= escape($name) ?>"
	data-id="<?= escape($id) ?>"
	<?= is_int($max) ? 'data-max="' . $max . '"' : '' ?>>
	<?php foreach ($rows as $index => $rowData) {
		if (!is_array($rowData)) {
			continue;
		}

		$type = $rowData['type'] ?? null;

		if (!is_string($type) || !isset($entryTypes[$type])) {
			// Rendered without inputs: rows of types no longer allowed
			// cannot be edited and are dropped on the next save.
			echo '<div class="cms-control-unknown">';
			echo escape(__('field:unknown-entry-type', ['type' => (string) $type]));
			echo '</div>';

			continue;
		}

		$row($index, $rowData, $entryTypes[$type]);
	} ?>
	<?php foreach ($entryTypes as $entryType): ?>
		<template data-repeater-template="<?= escape((string) $entryType['type']) ?>">
			<?php $row('__i__', null, $entryType) ?>
		</template>
	<?php endforeach ?>
	<div class="foot" data-repeater-footer>
		<?php foreach ($entryTypes as $entryType): ?>
			<button
				type="button"
				class="cms-button"
				data-repeater-add="<?= escape((string) $entryType['type']) ?>"
				<?= $full ? 'hidden' : '' ?>>
				<?= escape($addLabel($entryType, $rows === [])) ?>
			</button>
		<?php endforeach ?>
	</div>
</div>
