<?php

use Cosray\Block\Layout;

$field = (array) $this->unwrap($field);
$index = $this->unwrap($index);
$rowData = $this->unwrap($rowData ?? null);
$rowData = is_array($rowData) ? $rowData : null;
$blockType = (array) $this->unwrap($blockType);
$blockTypes = (array) $this->unwrap($blockTypes);
$columns = max(1, (int) $columns);
$min = min($columns, max(1, (int) $min));
$metaControl = $this->unwrap($metaControl ?? null);
$metaControl = is_array($metaControl) ? $metaControl : null;
$single = $this->unwrap($single ?? null);
$single = is_string($single) ? $single : null;
$name = (string) $this->unwrap($name);
$id = (string) $this->unwrap($id);

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
	style="<?= $this->escape($style) ?>">
	<input
		type="hidden"
		data-repeater-uid
		name="<?= $this->escape("{$rowName}[uid]") ?>"
		value="<?= $this->escape($uid) ?>" />
	<input
		type="hidden"
		name="<?= $this->escape("{$rowName}[type]") ?>"
		value="<?= $this->escape((string) $blockType['type']) ?>" />
	<input
		type="hidden"
		name="<?= $this->escape("{$rowName}[layout][span]") ?>"
		value="<?= $layout->span ?>"
		data-layout="span" />
	<input
		type="hidden"
		name="<?= $this->escape("{$rowName}[layout][rows]") ?>"
		value="<?= $layout->rows ?>"
		data-layout="rows" />
	<input
		type="hidden"
		name="<?= $this->escape("{$rowName}[layout][indent]") ?>"
		value="<?= $layout->indent ?>"
		data-layout="indent" />
	<div class="toolbar">
		<span class="grip" data-repeater-grip title="<?= $this->escape(__('field:drag-block')) ?>">
			<?php $this->insert('icon/grip.svg') ?>
		</span>
		<span class="kind"><?= $this->escape($label) ?></span>
		<?php if ($columns > 1): ?>
			<span class="span" title="<?= $this->escape(__('field:span')) ?>">
				<?php $this->insert('field/blocks/stepper', [
					'dimension' => 'span',
					'value' => $layout->span,
					'low' => $min,
					'high' => $columns,
					'less' => __('field:span-decrease'),
					'more' => __('field:span-increase'),
				]) ?>
			</span>
		<?php endif ?>
		<?php if ($metaControl !== null): ?>
			<button
				type="button"
				class="gear"
				data-meta-open
				aria-label="<?= $this->escape(__('field:block-settings')) ?>"
				title="<?= $this->escape(__('field:block-settings')) ?>">
				<?php $this->insert('icon/gear.svg') ?>
			</button>
		<?php endif ?>
		<details class="kebab" data-repeater-menu>
			<summary aria-label="<?= $this->escape(__('field:block-actions')) ?>"></summary>
			<div class="kebab-menu">
				<?php if ($single !== null): ?>
					<button
						type="button"
						data-repeater-add="<?= $this->escape($single) ?>"
						data-repeater-insert="before">
						<?= $this->escape(__('field:insert-above')) ?>
					</button>
					<button
						type="button"
						data-repeater-add="<?= $this->escape($single) ?>"
						data-repeater-insert="after">
						<?= $this->escape(__('field:insert-below')) ?>
					</button>
				<?php else: ?>
					<details class="picker">
						<summary><?= $this->escape(__('field:insert-above')) ?></summary>
						<div class="picker-menu">
							<?php $this->insert('field/blocks/picker', [
								'blockTypes' => $blockTypes,
								'insert' => 'before',
							]) ?>
						</div>
					</details>
					<details class="picker">
						<summary><?= $this->escape(__('field:insert-below')) ?></summary>
						<div class="picker-menu">
							<?php $this->insert('field/blocks/picker', [
								'blockTypes' => $blockTypes,
								'insert' => 'after',
							]) ?>
						</div>
					</details>
				<?php endif ?>
				<button type="button" data-repeater-move="up">
					<?= $this->escape(__('common:move-up')) ?>
				</button>
				<button type="button" data-repeater-move="down">
					<?= $this->escape(__('common:move-down')) ?>
				</button>
				<?php if ($columns > 1): ?>
					<?php // The width row only shows where the toolbar folded

					// it away (a narrow block, by container query). ?>
					<div class="layout is-span">
						<span class="name"><?= $this->escape(__('field:span')) ?></span>
						<?php $this->insert('field/blocks/stepper', [
							'dimension' => 'span',
							'value' => $layout->span,
							'low' => $min,
							'high' => $columns,
							'less' => __('field:span-decrease'),
							'more' => __('field:span-increase'),
						]) ?>
					</div>
					<div class="layout">
						<span class="name"><?= $this->escape(__('field:rows')) ?></span>
						<?php $this->insert('field/blocks/stepper', [
							'dimension' => 'rows',
							'value' => $layout->rows,
							'low' => 1,
							'high' => Layout::MAX_ROWS,
							'less' => __('field:rows-decrease'),
							'more' => __('field:rows-increase'),
						]) ?>
					</div>
					<div class="layout">
						<span class="name"><?= $this->escape(__('field:indent')) ?></span>
						<?php $this->insert('field/blocks/stepper', [
							'dimension' => 'indent',
							'value' => $layout->indent,
							'low' => 0,
							'high' => $columns - $layout->span,
							'less' => __('field:indent-decrease'),
							'more' => __('field:indent-increase'),
						]) ?>
					</div>
				<?php endif ?>
				<button type="button" data-repeater-remove>
					<?= $this->escape(__('field:remove-block')) ?>
				</button>
			</div>
		</details>
	</div>
	<div class="body cms-fields" id="<?= $this->escape("{$rowId}-form") ?>">
		<?php $this->insert('field/row-fields', [
			'type' => $blockType,
			'fieldsData' => $fieldsData,
			'rowName' => $rowName,
			'rowId' => $rowId,
		]) ?>
	</div>
	<?php if ($metaControl !== null): ?>
		<dialog class="cms-meta" data-meta>
			<div class="head">
				<span class="title">
					<?= $this->escape($label) ?> — <?= $this->escape(__('field:block-settings')) ?>
				</span>
				<button type="button" class="cms-button" data-meta-close>
					<?= $this->escape(__('field:close')) ?>
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
