<?php

use Cosray\Block\Layout;
use Cosray\Panel\RowLocales;

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
$ownsLocales = RowLocales::owned($blockType, count((array) $this->unwrap($locales)));
$reserved = $layout->indent + $layout->span;
$style = "--span: {$layout->span}; --rows: {$layout->rows}; --indent: {$layout->indent}; --reserved: {$reserved}";
$settings = $metaControl !== null || $columns > 1;
?>
<div
	class="block"
	data-repeater-row
	<?= $ownsLocales ? 'data-locale-scope' : '' ?>
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
	<?php // Where a new block lands: in a list on the seam above this one,

	// in a grid before or after it in order, from either side edge. ?>
	<?php $this->insert('field/blocks/inserter', [
		'blockTypes' => $blockTypes,
		'single' => $single,
		'insert' => 'before',
		'label' => __($columns > 1 ? 'field:insert-before' : 'field:insert-above'),
	]) ?>
	<div class="chrome">
		<span class="tools">
			<span class="grip" data-repeater-grip title="<?= $this->escape(__('field:drag-block')) ?>">
				<?php $this->insert('icon/grip.svg') ?>
			</span>
			<span class="kind"><?= $this->escape($label) ?></span>
			<?php if ($settings): ?>
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
					<button type="button" data-repeater-move="up">
						<?= $this->escape(__('common:move-up')) ?>
					</button>
					<button type="button" data-repeater-move="down">
						<?= $this->escape(__('common:move-down')) ?>
					</button>
					<button type="button" data-repeater-duplicate>
						<?= $this->escape(__('field:duplicate-block')) ?>
					</button>
					<button type="button" data-repeater-remove>
						<?= $this->escape(__('field:remove-block')) ?>
					</button>
				</div>
			</details>
		</span>
		<?php if ($ownsLocales) {
			$this->insert('field/row-locales');
		} ?>
	</div>
	<?php if ($columns > 1): ?>
		<?php foreach (
			[
				'start' => __('field:indent'),
				'end' => __('field:span'),
				'bottom' => __('field:rows'),
			] as $edge => $title
		): ?>
			<span
				class="resize is-<?= $edge ?>"
				data-layout-resize="<?= $edge ?>"
				aria-hidden="true"
				title="<?= $this->escape($title) ?>"></span>
		<?php endforeach ?>
	<?php endif ?>
	<div class="body cms-fields" id="<?= $this->escape("{$rowId}-form") ?>">
		<?php $this->insert('field/row-fields', [
			'type' => $blockType,
			'ownsLocales' => $ownsLocales,
			// One visible field needs no label of its own: the block names it.
			'labels' => (bool) ($blockType['labels'] ?? true),
			'fieldsData' => $fieldsData,
			'rowName' => $rowName,
			'rowId' => $rowId,
		]) ?>
	</div>
	<?php if ($columns > 1) {
		$this->insert('field/blocks/inserter', [
			'blockTypes' => $blockTypes,
			'single' => $single,
			'insert' => 'after',
			'label' => __('field:insert-after'),
		]);
	} ?>
	<?php if ($settings): ?>
		<dialog class="cms-meta" data-meta>
			<div class="head">
				<span class="title">
					<?= $this->escape($label) ?> — <?= $this->escape(__('field:block-settings')) ?>
				</span>
				<button type="button" class="cms-button" data-meta-close>
					<?= $this->escape(__('field:close')) ?>
				</button>
			</div>
			<?php if ($columns > 1) {
				$this->insert('field/blocks/layout', [
					'layout' => $layout->array(),
					'columns' => $columns,
					'min' => $min,
					'id' => "{$rowId}-layout",
				]);
			} ?>
			<?php if ($metaControl !== null) {
				$this->insert('field/meta', [
					'field' => $field,
					'control' => $metaControl,
					'meta' => $rowData['meta'] ?? null,
					'id' => "{$rowId}-meta",
					'nameRoot' => $rowName,
				]);
			} ?>
		</dialog>
	<?php endif ?>
</div>
