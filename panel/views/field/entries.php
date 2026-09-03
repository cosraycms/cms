<?php

// Server-rendered entries: a typed repeater whose rows are groups of
// regular field wrappers (field/row-fields). Add/remove/move/renumber is
// wired by the repeater behavior; one inert template per allowed entry
// type stamps fresh rows entirely client-side. Stored rows render
// collapsed to a summary line and open their form beneath it; stamped
// rows open expanded. Receives the neutral-locale row list in $value and
// the renumber base (content[f][value][zxx]) in $name.

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$value = $this->unwrap($value ?? null);
$rows = is_array($value) ? array_values($value) : [];
$max = $props['max'] ?? null;

$entryTypes = [];

foreach ((array) ($props['entryTypes'] ?? []) as $entryType) {
	if (is_array($entryType) && is_string($entryType['type'] ?? null)) {
		$entryTypes[$entryType['type']] = $entryType;
	}
}

$count = count($rows);
$full = is_int($max) && $max > 0 && $count >= $max;
$single = count($entryTypes) === 1;
?>
<div
	class="cms-entries"
	data-repeater
	data-name="<?= $this->escape($name) ?>"
	data-id="<?= $this->escape($id) ?>"
	<?= is_int($max) ? 'data-max="' . $max . '"' : '' ?>>
	<div
		class="tally"
		data-repeater-count
		data-one="<?= $this->escape(__('field:entry-count')) ?>"
		data-many="<?= $this->escape(__('field:entry-count-plural')) ?>"><?= $this->escape(
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
				echo $this->escape(__('field:unknown-entry-type', ['type' => (string) $type]));
				echo '</div>';

				continue;
			}

			$this->insert('field/entries/row', [
				'index' => $index,
				'rowData' => $rowData,
				'entryType' => $entryTypes[$type],
			]);
		} ?>
	</div>
	<?php foreach ($entryTypes as $entryType): ?>
		<template data-repeater-template="<?= $this->escape((string) $entryType['type']) ?>">
			<?php $this->insert('field/entries/row', [
				'index' => '__i__',
				'rowData' => null,
				'entryType' => $entryType,
			]) ?>
		</template>
	<?php endforeach ?>
	<div class="adders" data-repeater-footer>
		<?php foreach ($entryTypes as $entryType): ?>
			<?php $this->insert('field/entries/adder', [
				'entryType' => $entryType,
				'empty' => $rows === [],
				'full' => $full,
				'single' => $single,
			]) ?>
		<?php endforeach ?>
	</div>
</div>
