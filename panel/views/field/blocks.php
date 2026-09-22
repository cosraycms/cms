<?php

// Server-rendered blocks: the entries typed repeater with a grid. Rows
// are placed on a preview grid mirroring the frontend contract
// (--columns on the container, --colspan/--rowspan/--indent on the row) and
// carry their layout as hidden inputs; the blocks behavior edits them.
// Add/remove/move/renumber comes from the repeater behavior: a + on each
// row stamps before or after the row it sits in, the footer appends,
// with a type picker for row insertions and multi-type footers.
// Rows are never collapsed. A split renders through its own partial,
// which holds its parts; one container template per field variant is
// what the split behavior stamps a new split from.
// Receives one row list in $value and its renumber base — per locale
// for an asymmetric field, the neutral locale otherwise — in $name.

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$value = $this->unwrap($value ?? null);
$rows = is_array($value) ? array_values($value) : [];
$columns = max(1, (int) ($props['columns'] ?? 1));
$min = min($columns, max(1, (int) ($props['min'] ?? 1)));
$metaControl = is_array($props['meta'] ?? null) ? $props['meta'] : null;
// The field's own settings: the gap tokens the canvas renders like the site.
$data = $this->unwrap($data ?? null);
$meta = is_array($data) && is_array($data['meta'] ?? null) ? $data['meta'] : [];
$spacing = static fn(string $key): string => is_array($meta[$key] ?? null)
	&& in_array($meta[$key]['zxx'] ?? null, \Cosray\Field\Blocks::SPACING, true)
		? (string) $meta[$key]['zxx']
		: '';

$field = (array) $this->unwrap($field);
$choices = new \Cosray\Panel\BlockChoices(
	(string) ($field['name'] ?? ''),
	$props,
	isset($renderIcon) ? fn(array $icon): string => (string) $this->unwrap($renderIcon($icon)) : null,
);
$blockTypes = $choices->types;
$commonChoices = $choices->common;
$more = count($choices->all) > count($commonChoices);

$single = count($blockTypes) === 1 ? array_key_first($blockTypes) : null;
// An immutable field locks its canvas as well: the save path ignores the
// whole field, so offering add, remove, move or resize would only lose
// the work.
$readonly = (bool) ($field['immutable'] ?? false);
?>
<div
	class="cms-blocks-editor<?= $columns > 1 ? ' is-grid' : ' is-list' ?>"
	data-repeater
	data-name="<?= $this->escape($name) ?>"
	data-id="<?= $this->escape($id) ?>"
	data-columns="<?= $columns ?>"
	data-min="<?= $min ?>"
	<?= $columns > 1 ? 'data-ghost-label="' . $this->escape(__('field:add-block-here')) . '"' : '' ?>
	<?= $spacing('gap') !== '' ? 'data-gap="' . $this->escape($spacing('gap')) . '"' : '' ?>
	<?= $spacing('rowGap') !== '' ? 'data-row-gap="' . $this->escape($spacing('rowGap')) . '"' : '' ?>
	<?= $spacing('columnGap') !== '' ? 'data-column-gap="' . $this->escape($spacing('columnGap')) . '"' : '' ?>
	style="--columns: <?= $columns ?>">
	<div class="grid" data-repeater-list>
		<?php foreach ($rows as $index => $rowData) {
			if (!is_array($rowData)) {
				continue;
			}

			$type = $rowData['type'] ?? null;

			if ($type === null && is_array($rowData['blocks'] ?? null)) {
				$this->insert('field/blocks/split', [
					'index' => $index,
					'rowData' => $rowData,
					'blockTypes' => $blockTypes,
					'commonChoices' => $commonChoices,
					'more' => $more,
					'columns' => $columns,
					'min' => $min,
					'metaControl' => $metaControl,
					'readonly' => $readonly,
				]);

				continue;
			}

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
				'commonChoices' => $commonChoices,
				'more' => $more,
				'columns' => $columns,
				'min' => $min,
				'metaControl' => $metaControl,
				'readonly' => $readonly,
			]);
		} ?>
	</div>
	<?php if ($readonly): ?>
		<?php // No templates, no catalog, no adders: nothing stamps a row here. ?>
	<?php else: ?>
	<?php foreach ($blockTypes as $blockType): ?>
		<template data-repeater-template="<?= $this->escape((string) $blockType['type']) ?>">
			<?php $this->insert('field/blocks/row', [
				'index' => '__i__',
				'rowData' => null,
				'blockType' => $blockType,
				'blockTypes' => $blockTypes,
				'commonChoices' => $commonChoices,
				'more' => $more,
				'columns' => $columns,
				'min' => $min,
				'metaControl' => $metaControl,
				'readonly' => false,
			]) ?>
		</template>
	<?php endforeach ?>
	<?php if ($columns > 1): ?>
		<template data-repeater-container>
			<?php $this->insert('field/blocks/split', [
				'index' => '__i__',
				'rowData' => null,
				'blockTypes' => $blockTypes,
				'commonChoices' => $commonChoices,
				'more' => $more,
				'columns' => $columns,
				'min' => $min,
				'metaControl' => $metaControl,
				'readonly' => false,
			]) ?>
		</template>
	<?php endif ?>
	<?php if ($more) {
		$this->insert('field/blocks/catalog', ['choices' => $choices->all]);
	} ?>
	<div class="adders" data-repeater-footer>
		<?php if ($blockTypes === []): ?>
			<span><?= $this->escape(__('field:no-block-types')) ?></span>
		<?php elseif ($single !== null): ?>
			<button
				type="button"
				class="adder"
				data-repeater-add="<?= $this->escape($single) ?>"
				data-repeater-insert="append">
				<?= \Cosray\Panel\Icon::render('plus-circle') ?>
				<?= $this->escape(
					__('field:add-typed', ['label' => (string) ($blockTypes[$single]['label'] ?? __('field:block'))]),
				) ?>
			</button>
		<?php else: ?>
			<button
				type="button"
				class="adder"
				id="<?= $this->escape($id . '-picker-trigger') ?>"
				popovertarget="<?= $this->escape($id . '-picker') ?>"
				aria-haspopup="menu"
			>
				<?= \Cosray\Panel\Icon::render('plus-circle') ?>
				<?= $this->escape(__('field:add-block')) ?>
			</button>
			<div
				id="<?= $this->escape($id . '-picker') ?>"
				class="cms-action-menu"
				popover="auto"
				data-action-menu
				data-align="center"
			>
				<?php $this->insert('field/blocks/picker', [
					'commonChoices' => $commonChoices,
					'more' => $more,
					'insert' => 'append',
				]) ?>
			</div>
		<?php endif ?>
	</div>
	<?php endif ?>
</div>
