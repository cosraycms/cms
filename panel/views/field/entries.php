<?php

use Cosray\Panel\EntrySummary;

use function Cosray\escape;

// Server-rendered entries: a typed repeater whose rows are groups of
// regular field wrappers (field/row-fields). Add/remove/move/renumber is
// wired by the repeater behavior; one inert template per allowed entry
// type stamps fresh rows entirely client-side. Stored rows render
// collapsed to a summary line and open their form beneath it; stamped
// rows open expanded. Receives the neutral-locale row list in $value and
// the renumber base (content[f][value][zxx]) in $name.

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

$row = function (int|string $index, ?array $rowData, array $entryType) use (
	$name,
	$id,
	$locales,
	$defaultLocale,
	$node,
	$assets,
): void {
	$rowName = "{$name}[{$index}]";
	$rowId = "{$id}-{$index}";
	$uid = is_string($rowData['uid'] ?? null) ? $rowData['uid'] : '';
	$fieldsData = is_array($rowData['fields'] ?? null) ? $rowData['fields'] : [];
	$label = (string) ($entryType['label'] ?? __('field:entry'));
	$summary = EntrySummary::of($entryType, $fieldsData, $assets, $defaultLocale);
	// Stored rows start collapsed; a stamped row is empty and wants input.
	$open = $rowData === null;
	?>
	<div class="entry" data-repeater-row>
		<div class="summary">
			<span class="grip" data-repeater-grip title="<?= escape(__('field:drag-entry')) ?>">
				<?php $this->insert('icon/grip.svg') ?>
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
					<?php // Each line names the sub-field it came from, so the

					// behavior refreshes only that line while its input changes. ?>
					<span
						class="primary"
						data-repeater-title="<?= escape((string) $summary->primaryField) ?>"
						data-fallback="<?= escape($label) ?>"><?= escape($summary->primary ?? $label) ?></span>
					<span
						class="secondary"
						data-repeater-subtitle="<?= escape((string) $summary->secondaryField) ?>"><?= escape(
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
			<?php $this->insert('field/row-fields', [
				'type' => $entryType,
				'fieldsData' => $fieldsData,
				'rowName' => $rowName,
				'rowId' => $rowId,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
				'node' => $node,
				'assets' => $assets,
			]) ?>
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
				<?php $this->insert('icon/plus.svg') ?>
				<?= escape($addLabel($entryType, $rows === [])) ?>
			</button>
		<?php endforeach ?>
	</div>
</div>
