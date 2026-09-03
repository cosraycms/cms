<?php

// Server-rendered blocks: the entries typed repeater with a grid. Rows
// are placed on a preview grid mirroring the frontend contract
// (--columns on the container, --span/--rows/--indent on the row) and
// carry their layout as hidden inputs; the blocks behavior steps them.
// Add/remove/move/renumber comes from the repeater behavior; the type
// picker is a <details> menu whose buttons stamp before or after the
// row they sit in, or append from the footer. Rows are never collapsed.
// Receives one row list in $value and its renumber base — per locale
// for an asymmetric field, the neutral locale otherwise — in $name.

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$value = $this->unwrap($value ?? null);
$rows = is_array($value) ? array_values($value) : [];
$columns = max(1, (int) ($props['columns'] ?? 1));
$min = min($columns, max(1, (int) ($props['min'] ?? 1)));
$metaControl = is_array($props['meta'] ?? null) ? $props['meta'] : null;

$blockTypes = [];

foreach ((array) ($props['blockTypes'] ?? []) as $blockType) {
	if (is_array($blockType) && is_string($blockType['type'] ?? null)) {
		$blockTypes[$blockType['type']] = $blockType;
	}
}

$single = count($blockTypes) === 1 ? array_key_first($blockTypes) : null;
$count = count($rows);
?>
<div
	class="cms-blocks-editor<?= $columns > 1 ? ' is-grid' : ' is-list' ?>"
	data-repeater
	data-name="<?= $this->escape($name) ?>"
	data-id="<?= $this->escape($id) ?>"
	data-columns="<?= $columns ?>"
	data-min="<?= $min ?>"
	style="--columns: <?= $columns ?>">
	<div
		class="tally"
		data-repeater-count
		data-one="<?= $this->escape(__('field:block-count')) ?>"
		data-many="<?= $this->escape(__('field:block-count-plural')) ?>"><?= $this->escape(
			__($count === 1 ? 'field:block-count' : 'field:block-count-plural', ['count' => $count]),
		) ?></div>
	<div class="grid" data-repeater-list>
		<?php foreach ($rows as $index => $rowData) {
			if (!is_array($rowData)) {
				continue;
			}

			$type = $rowData['type'] ?? null;

			if (!is_string($type) || !isset($blockTypes[$type])) {
				// Rendered without inputs: rows of types no longer allowed
				// cannot be edited and are dropped on the next save.
				echo '<div class="cms-control-unknown">';
				echo $this->escape(__('field:unknown-block-type', ['type' => (string) $type]));
				echo '</div>';

				continue;
			}

			$this->insert('field/blocks/row', [
				'index' => $index,
				'rowData' => $rowData,
				'blockType' => $blockTypes[$type],
				'blockTypes' => $blockTypes,
				'columns' => $columns,
				'min' => $min,
				'metaControl' => $metaControl,
				'single' => $single,
			]);
		} ?>
	</div>
	<?php foreach ($blockTypes as $blockType): ?>
		<template data-repeater-template="<?= $this->escape((string) $blockType['type']) ?>">
			<?php $this->insert('field/blocks/row', [
				'index' => '__i__',
				'rowData' => null,
				'blockType' => $blockType,
				'blockTypes' => $blockTypes,
				'columns' => $columns,
				'min' => $min,
				'metaControl' => $metaControl,
				'single' => $single,
			]) ?>
		</template>
	<?php endforeach ?>
	<div class="adders" data-repeater-footer>
		<?php if ($single !== null): ?>
			<button
				type="button"
				class="adder"
				data-repeater-add="<?= $this->escape($single) ?>"
				data-repeater-insert="append">
				<?php $this->insert('icon/plus.svg') ?>
				<?= $this->escape(
					__('field:add-typed', ['label' => (string) ($blockTypes[$single]['label'] ?? __('field:block'))]),
				) ?>
			</button>
		<?php else: ?>
			<details class="picker" data-repeater-menu>
				<summary class="adder">
					<?php $this->insert('icon/plus.svg') ?>
					<?= $this->escape(__('field:add-block')) ?>
				</summary>
				<div class="picker-menu">
					<?php $this->insert('field/blocks/picker', [
						'blockTypes' => $blockTypes,
						'insert' => 'append',
					]) ?>
				</div>
			</details>
		<?php endif ?>
	</div>
</div>
