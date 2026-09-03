<?php

use Cosray\Block\Layout;

use function Cosray\escape;

// Server-rendered blocks: the entries typed repeater with a grid. Rows
// are placed on a preview grid mirroring the frontend contract
// (--columns on the container, --span/--rows/--indent on the row) and
// carry their layout as hidden inputs; the blocks behavior steps them.
// Add/remove/move/renumber comes from the repeater behavior; the type
// picker is a <details> menu whose buttons stamp before or after the
// row they sit in, or append from the footer. Rows are never collapsed.
// Receives one row list in $value and its renumber base — per locale
// for an asymmetric field, the neutral locale otherwise — in $name.

$field = (array) $this->unwrap($field);
$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$value = $this->unwrap($value ?? null);
$rows = is_array($value) ? array_values($value) : [];
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$node = (string) ($node ?? '');
$assets = (array) ($this->unwrap($assets ?? null) ?? []);
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

$grip = '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">'
	. '<path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 '
	. '1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 '
	. '1 1-2 0 1 1 0 0 1 2 0zM7 11a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 '
	. '14a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/></svg>';
$plus = '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">'
	. '<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>'
	. '<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 '
	. '0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>';
$gear = '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">'
	. '<path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 '
	. '0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>'
	. '<path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16'
	. 'c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 '
	. '3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292'
	. '-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 '
	. '1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094'
	. 'c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433'
	. '-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319z"/></svg>';

// The type picker: one button per allowed type, stamping at $insert.
$picker = function (string $insert) use ($blockTypes): void {
	foreach ($blockTypes as $blockType): ?>
		<button
			type="button"
			data-repeater-add="<?= escape((string) $blockType['type']) ?>"
			data-repeater-insert="<?= escape($insert) ?>">
			<?= escape((string) ($blockType['label'] ?? __('field:block'))) ?>
		</button>
	<?php endforeach;
};

// A stepper: −, the value badge, +; the boundary buttons start disabled
// and the behavior keeps them so while stepping.
$stepper = static function (string $dimension, int $value, int $low, int $high, string $less, string $more): void {
	?>
	<span class="stepper">
		<button
			type="button"
			class="step"
			data-layout-step="<?= escape("{$dimension}:-1") ?>"
			aria-label="<?= escape($less) ?>"
			title="<?= escape($less) ?>"
			<?= $value <= $low ? 'disabled' : '' ?>>−</button>
		<span class="badge" data-layout-badge="<?= escape($dimension) ?>"><?= $value ?></span>
		<button
			type="button"
			class="step"
			data-layout-step="<?= escape("{$dimension}:+1") ?>"
			aria-label="<?= escape($more) ?>"
			title="<?= escape($more) ?>"
			<?= $value >= $high ? 'disabled' : '' ?>>+</button>
	</span>
	<?php
};

$row = function (int|string $index, ?array $rowData, array $blockType) use (
	$field,
	$name,
	$id,
	$columns,
	$min,
	$metaControl,
	$single,
	$locales,
	$defaultLocale,
	$node,
	$assets,
	$grip,
	$gear,
	$picker,
	$stepper,
): void {
	$rowName = "{$name}[{$index}]";
	$rowId = "{$id}-{$index}";
	$uid = is_string($rowData['uid'] ?? null) ? $rowData['uid'] : '';
	$fieldsData = is_array($rowData['fields'] ?? null) ? $rowData['fields'] : [];
	$label = (string) ($blockType['label'] ?? __('field:block'));
	// A stored layout a narrower field cannot hold is shown clamped, as
	// the save will store it.
	$layout = Layout::normalize($rowData['layout'] ?? null, $columns, $min);
	$style = "--span: {$layout->span}; --rows: {$layout->rows}; --indent: {$layout->indent}";
	?>
	<div
		class="block"
		data-repeater-row
		data-meta-owner
		data-indent="<?= $layout->indent ?>"
		style="<?= $style ?>">
		<input
			type="hidden"
			data-repeater-uid
			name="<?= escape("{$rowName}[uid]") ?>"
			value="<?= escape($uid) ?>" />
		<input
			type="hidden"
			name="<?= escape("{$rowName}[type]") ?>"
			value="<?= escape((string) $blockType['type']) ?>" />
		<input
			type="hidden"
			name="<?= escape("{$rowName}[layout][span]") ?>"
			value="<?= $layout->span ?>"
			data-layout="span" />
		<input
			type="hidden"
			name="<?= escape("{$rowName}[layout][rows]") ?>"
			value="<?= $layout->rows ?>"
			data-layout="rows" />
		<input
			type="hidden"
			name="<?= escape("{$rowName}[layout][indent]") ?>"
			value="<?= $layout->indent ?>"
			data-layout="indent" />
		<div class="toolbar">
			<span class="grip" data-repeater-grip title="<?= escape(__('field:drag-block')) ?>">
				<?= $grip ?>
			</span>
			<span class="kind"><?= escape($label) ?></span>
			<?php if ($columns > 1): ?>
				<span class="span" title="<?= escape(__('field:span')) ?>">
					<?php $stepper(
						'span',
						$layout->span,
						$min,
						$columns,
						__('field:span-decrease'),
						__('field:span-increase'),
					) ?>
				</span>
			<?php endif ?>
			<?php if ($metaControl !== null): ?>
				<button
					type="button"
					class="gear"
					data-meta-open
					aria-label="<?= escape(__('field:block-settings')) ?>"
					title="<?= escape(__('field:block-settings')) ?>">
					<?= $gear ?>
				</button>
			<?php endif ?>
			<details class="kebab" data-repeater-menu>
				<summary aria-label="<?= escape(__('field:block-actions')) ?>"></summary>
				<div class="kebab-menu">
					<?php if ($single !== null): ?>
						<button type="button" data-repeater-add="<?= escape($single) ?>" data-repeater-insert="before">
							<?= escape(__('field:insert-above')) ?>
						</button>
						<button type="button" data-repeater-add="<?= escape($single) ?>" data-repeater-insert="after">
							<?= escape(__('field:insert-below')) ?>
						</button>
					<?php else: ?>
						<details class="picker">
							<summary><?= escape(__('field:insert-above')) ?></summary>
							<div class="picker-menu"><?php $picker('before') ?></div>
						</details>
						<details class="picker">
							<summary><?= escape(__('field:insert-below')) ?></summary>
							<div class="picker-menu"><?php $picker('after') ?></div>
						</details>
					<?php endif ?>
					<button type="button" data-repeater-move="up">
						<?= escape(__('common:move-up')) ?>
					</button>
					<button type="button" data-repeater-move="down">
						<?= escape(__('common:move-down')) ?>
					</button>
					<?php if ($columns > 1): ?>
						<?php // The width row only shows where the toolbar folded

						// it away (a narrow block, by container query). ?>
						<div class="layout is-span">
							<span class="name"><?= escape(__('field:span')) ?></span>
							<?php $stepper(
								'span',
								$layout->span,
								$min,
								$columns,
								__('field:span-decrease'),
								__('field:span-increase'),
							) ?>
						</div>
						<div class="layout">
							<span class="name"><?= escape(__('field:rows')) ?></span>
							<?php $stepper(
								'rows',
								$layout->rows,
								1,
								Layout::MAX_ROWS,
								__('field:rows-decrease'),
								__('field:rows-increase'),
							) ?>
						</div>
						<div class="layout">
							<span class="name"><?= escape(__('field:indent')) ?></span>
							<?php $stepper(
								'indent',
								$layout->indent,
								0,
								$columns - $layout->span,
								__('field:indent-decrease'),
								__('field:indent-increase'),
							) ?>
						</div>
					<?php endif ?>
					<button type="button" data-repeater-remove>
						<?= escape(__('field:remove-block')) ?>
					</button>
				</div>
			</details>
		</div>
		<div class="body cms-fields" id="<?= escape("{$rowId}-form") ?>">
			<?php $this->insert('field/row-fields', [
				'type' => $blockType,
				'fieldsData' => $fieldsData,
				'rowName' => $rowName,
				'rowId' => $rowId,
				'locales' => $locales,
				'defaultLocale' => $defaultLocale,
				'node' => $node,
				'assets' => $assets,
			]) ?>
		</div>
		<?php if ($metaControl !== null): ?>
			<dialog class="cms-meta" data-meta>
				<div class="head">
					<span class="title">
						<?= escape($label) ?> — <?= escape(__('field:block-settings')) ?>
					</span>
					<button type="button" class="cms-button" data-meta-close>
						<?= escape(__('field:close')) ?>
					</button>
				</div>
				<?php $this->insert('field/meta', [
					'field' => $field,
					'control' => $metaControl,
					'meta' => $rowData['meta'] ?? null,
					'id' => "{$rowId}-meta",
					'nameRoot' => $rowName,
				]) ?>
			</dialog>
		<?php endif ?>
	</div>
	<?php
};

$count = count($rows);
?>
<div
	class="cms-blocks-editor<?= $columns > 1 ? ' is-grid' : ' is-list' ?>"
	data-repeater
	data-name="<?= escape($name) ?>"
	data-id="<?= escape($id) ?>"
	data-columns="<?= $columns ?>"
	data-min="<?= $min ?>"
	style="--columns: <?= $columns ?>">
	<div
		class="tally"
		data-repeater-count
		data-one="<?= escape(__('field:block-count')) ?>"
		data-many="<?= escape(__('field:block-count-plural')) ?>"><?= escape(
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
				echo escape(__('field:unknown-block-type', ['type' => (string) $type]));
				echo '</div>';

				continue;
			}

			$row($index, $rowData, $blockTypes[$type]);
		} ?>
	</div>
	<?php foreach ($blockTypes as $blockType): ?>
		<template data-repeater-template="<?= escape((string) $blockType['type']) ?>">
			<?php $row('__i__', null, $blockType) ?>
		</template>
	<?php endforeach ?>
	<div class="adders" data-repeater-footer>
		<?php if ($single !== null): ?>
			<button type="button" class="adder" data-repeater-add="<?= escape($single) ?>" data-repeater-insert="append">
				<?= $plus ?>
				<?= escape(__('field:add-typed', ['label' => (string) ($blockTypes[$single]['label'] ?? __('field:block'))])) ?>
			</button>
		<?php else: ?>
			<details class="picker" data-repeater-menu>
				<summary class="adder">
					<?= $plus ?>
					<?= escape(__('field:add-block')) ?>
				</summary>
				<div class="picker-menu"><?php $picker('append') ?></div>
			</details>
		<?php endif ?>
	</div>
</div>
