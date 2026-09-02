<?php

use Cosray\Panel\EntrySummary;

use function Cosray\escape;

// Server-rendered entries: a typed repeater whose rows are groups of
// regular field wrappers. Add/remove/move/renumber is wired by the
// repeater behavior; one inert template per allowed entry type stamps
// fresh rows entirely client-side. Stored rows render collapsed to a
// summary line and open their form beneath it; stamped rows open
// expanded. Receives the neutral-locale row list in $value and the
// renumber base (content[f][value][zxx]) in $name.

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

$grip = '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">'
	. '<path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 '
	. '1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 '
	. '1 1-2 0 1 1 0 0 1 2 0zM7 11a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 '
	. '14a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/></svg>';
$plus = '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">'
	. '<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>'
	. '<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 '
	. '0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>';

$row = function (int|string $index, ?array $rowData, array $entryType) use (
	$name,
	$id,
	$span,
	$renderField,
	$assets,
	$defaultLocale,
	$grip,
): void {
	$rowName = "{$name}[{$index}]";
	$rowId = "{$id}-{$index}";
	$uid = is_string($rowData['uid'] ?? null) ? $rowData['uid'] : '';
	$fieldsData = is_array($rowData['fields'] ?? null) ? $rowData['fields'] : [];
	$label = (string) ($entryType['label'] ?? __('field:entry'));
	$summary = EntrySummary::of($entryType, $fieldsData, $assets, $defaultLocale);
	// Stored rows start collapsed; a stamped row is empty and wants input.
	$open = $rowData === null;
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
	<div class="entry" data-repeater-row>
		<div class="summary">
			<span class="grip" data-repeater-grip title="<?= escape(__('field:drag-entry')) ?>">
				<?= $grip ?>
			</span>
			<button
				type="button"
				class="opener"
				data-repeater-collapse
				aria-expanded="<?= $open ? 'true' : 'false' ?>"
				aria-controls="<?= escape("{$rowId}-form") ?>">
				<?php if ($summary->hasImage): ?>
					<span class="thumb">
						<?php if ($summary->thumb !== null): ?>
							<img src="<?= escape($summary->thumb) ?>" alt="" />
						<?php endif ?>
					</span>
				<?php endif ?>
				<span class="texts">
					<span
						class="primary"
						data-repeater-title
						data-fallback="<?= escape($label) ?>"><?= escape($summary->primary ?? $label) ?></span>
					<span class="secondary" data-repeater-subtitle><?= escape(
						(string) $summary->secondary,
					) ?></span>
				</span>
			</button>
			<details class="kebab" data-repeater-menu>
				<summary aria-label="<?= escape(__('field:entry-actions')) ?>"></summary>
				<div class="kebab-menu">
					<button type="button" data-repeater-move="up">
						<?= escape(__('common:move-up')) ?>
					</button>
					<button type="button" data-repeater-move="down">
						<?= escape(__('common:move-down')) ?>
					</button>
					<button type="button" data-repeater-remove>
						<?= escape(__('field:remove-entry')) ?>
					</button>
				</div>
			</details>
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
		<div
			class="form cms-fields"
			id="<?= escape("{$rowId}-form") ?>"
			data-repeater-body
			<?= $open ? '' : 'hidden' ?>>
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

$count = count($rows);
$full = is_int($max) && $max > 0 && $count >= $max;
?>
<div
	class="cms-entries"
	data-repeater
	data-name="<?= escape($name) ?>"
	data-id="<?= escape($id) ?>"
	<?= is_int($max) ? 'data-max="' . $max . '"' : '' ?>>
	<div
		class="tally"
		data-repeater-count
		data-one="<?= escape(__('field:entry-count')) ?>"
		data-many="<?= escape(__('field:entry-count-plural')) ?>"><?= escape(
			__($count === 1 ? 'field:entry-count' : 'field:entry-count-plural', ['count' => $count]),
		) ?></div>
	<div class="rows" data-repeater-list>
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
	</div>
	<?php foreach ($entryTypes as $entryType): ?>
		<template data-repeater-template="<?= escape((string) $entryType['type']) ?>">
			<?php $row('__i__', null, $entryType) ?>
		</template>
	<?php endforeach ?>
	<div class="adders" data-repeater-footer>
		<?php foreach ($entryTypes as $entryType): ?>
			<button
				type="button"
				class="adder"
				data-repeater-add="<?= escape((string) $entryType['type']) ?>"
				<?= $full ? 'hidden' : '' ?>>
				<?= $plus ?>
				<?= escape($addLabel($entryType, $rows === [])) ?>
			</button>
		<?php endforeach ?>
	</div>
</div>
