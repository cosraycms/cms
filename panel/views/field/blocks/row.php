<?php

use Cosray\Block\Heading;
use Cosray\Block\Layout;

// One block, on the field's grid or as a part of a split: the same
// markup serves both, since a split moves blocks between the two. What
// differs by place is marked with data-places — `block` on the grid,
// `columns` or `rows` in a split of that direction — and shown by the
// editor styles, so a block moved in or out needs no new markup.

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
$name = (string) $this->unwrap($name);
$id = (string) $this->unwrap($id);

// The split a part sits in, which bounds its layout.
$area = $this->unwrap($area ?? null);
$area = $area instanceof Layout ? $area : null;

$rowName = "{$name}[{$index}]";
$rowId = "{$id}-{$index}";
$uid = is_string($rowData['uid'] ?? null) ? $rowData['uid'] : '';
$fieldsData = is_array($rowData['fields'] ?? null) ? $rowData['fields'] : [];
$label = (string) ($blockType['label'] ?? __('field:block'));

// A stored layout a narrower field cannot hold is shown clamped, as
// the save will store it.
$layout = $area === null
	? Layout::normalize($rowData['layout'] ?? null, $columns, $min)
	: Layout::normalize($rowData['layout'] ?? null, $area->colspan, $min, $area->rowspan);
$readonly = (bool) ($this->unwrap($readonly ?? null) ?? false);
$reserved = $layout->indent + $layout->colspan;
$padding = $rowData['meta']['padding']['zxx'] ?? null;
$padding = in_array($padding, \Cosray\Field\Blocks::SPACING, true) ? (string) $padding : '';
$style = "--colspan: {$layout->colspan}; --rowspan: {$layout->rowspan}; --indent: {$layout->indent}; --reserved: {$reserved}";
$labels = (bool) ($blockType['labels'] ?? true);
// A built-in type with an editor view of its own renders as content
// whatever its field count; the generic form keeps its labels.
$editor = match ($blockType['type'] ?? null) {
	Heading::class => 'field/blocks/types/heading',
	default => null,
};
$bare = !$labels || $editor !== null;
// Sub-fields with a meta group of their own edit it in the block's dialog.
$subMetas = array_values(array_filter(
	(array) ($blockType['fields'] ?? []),
	static fn(mixed $sub): bool => (
		is_array($sub)
		&& is_array($sub['metaControl'] ?? null)
		&& !($sub['hidden'] ?? false)
	),
));
// An element sub-field gets a slot in the dialog for the controls it keeps
// out of the content; the host hands the slot to the element.
$slots = array_values(array_filter(
	(array) ($blockType['fields'] ?? []),
	static fn(mixed $sub): bool => (
		is_array($sub)
		&& is_array($sub['control'] ?? null)
		&& ($sub['control']['name'] ?? null) === 'element'
		&& !($sub['hidden'] ?? false)
	),
));
$settings = $metaControl !== null || $columns > 1 || $subMetas !== [] || $slots !== [];
?>
<div
	class="block<?= $bare ? ' is-bare' : '' ?>"
	data-repeater-row
	data-meta-owner
	data-indent="<?= $layout->indent ?>"
	<?= $padding !== '' ? 'data-padding="' . $this->escape($padding) . '"' : '' ?>
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
		name="<?= $this->escape("{$rowName}[layout][colspan]") ?>"
		value="<?= $layout->colspan ?>"
		data-layout="colspan" />
	<input
		type="hidden"
		name="<?= $this->escape("{$rowName}[layout][rowspan]") ?>"
		value="<?= $layout->rowspan ?>"
		data-layout="rowspan" />
	<input
		type="hidden"
		name="<?= $this->escape("{$rowName}[layout][indent]") ?>"
		value="<?= $layout->indent ?>"
		data-layout="indent" />
	<?php if (!$readonly): ?>
	<div class="chrome">
		<span class="tools">
			<?php if ($columns > 1): ?>
				<span
					class="grip"
					data-repeater-grip
					tabindex="0"
					title="<?= $this->escape(__('field:drag-block')) ?>"
					aria-label="<?= $this->escape(__('field:drag-block') . '. ' . __('field:resize-block')) ?>"
					aria-keyshortcuts="Alt+ArrowLeft Alt+ArrowRight Alt+ArrowUp Alt+ArrowDown Alt+Shift+ArrowLeft Alt+Shift+ArrowRight">
					<?= \Cosray\Panel\Icon::render('grip-vertical') ?>
				</span>
			<?php else: ?>
				<span class="grip" data-repeater-grip title="<?= $this->escape(__('field:drag-block')) ?>">
					<?= \Cosray\Panel\Icon::render('grip-vertical') ?>
				</span>
			<?php endif ?>
			<?php $this->insert('field/blocks/inserter', [
				'commonChoices' => $commonChoices,
				'more' => $more,
				'id' => "{$rowId}-insert",
				'places' => $columns > 1 ? 'block' : null,
			]) ?>
			<?php if ($settings): ?>
				<button
					type="button"
					class="gear"
					data-meta-open
					aria-label="<?= $this->escape(__('field:block-settings')) ?>"
					title="<?= $this->escape(__('field:block-settings')) ?>">
					<?= \Cosray\Panel\Icon::render('pencil') ?>
				</button>
			<?php endif ?>
			<button type="button" class="kebab"
				popovertarget="<?= $this->escape("{$rowId}-actions") ?>"
				aria-haspopup="menu" aria-label="<?= $this->escape(__('field:block-actions')) ?>">
				<?= \Cosray\Panel\Icon::render('three-dots-vertical') ?>
			</button>
			<div id="<?= $this->escape("{$rowId}-actions") ?>" class="cms-action-menu"
				popover="auto" data-action-menu data-align="end">
				<?php if ($columns > 1): ?>
					<button type="button" data-repeater-move="up" data-places="block rows">
						<?= $this->escape(__('common:move-up')) ?>
					</button>
					<button type="button" data-repeater-move="down" data-places="block rows">
						<?= $this->escape(__('common:move-down')) ?>
					</button>
					<button type="button" data-repeater-move="up" data-places="columns">
						<?= $this->escape(__('common:move-left')) ?>
					</button>
					<button type="button" data-repeater-move="down" data-places="columns">
						<?= $this->escape(__('common:move-right')) ?>
					</button>
					<button type="button" data-repeater-duplicate data-places="block">
						<?= $this->escape(__('field:duplicate-block')) ?>
					</button>
					<button type="button" data-split-into="columns" data-places="block">
						<?= $this->escape(__('field:split-columns')) ?>
					</button>
					<button type="button" data-split-into="rows" data-places="block">
						<?= $this->escape(__('field:split-rows')) ?>
					</button>
					<button type="button" data-split-into="columns" data-places="columns">
						<?= $this->escape(__('field:split')) ?>
					</button>
					<button type="button" data-split-into="rows" data-places="rows">
						<?= $this->escape(__('field:split')) ?>
					</button>
					<button type="button" class="danger" data-repeater-remove data-places="block">
						<?= $this->escape(__('field:remove-block')) ?>
					</button>
					<button type="button" class="danger" data-split-remove data-places="columns rows">
						<?= $this->escape(__('field:remove-block')) ?>
					</button>
				<?php else: ?>
					<button type="button" data-repeater-move="up">
						<?= $this->escape(__('common:move-up')) ?>
					</button>
					<button type="button" data-repeater-move="down">
						<?= $this->escape(__('common:move-down')) ?>
					</button>
					<button type="button" data-repeater-duplicate>
						<?= $this->escape(__('field:duplicate-block')) ?>
					</button>
					<button type="button" class="danger" data-repeater-remove>
						<?= $this->escape(__('field:remove-block')) ?>
					</button>
				<?php endif ?>
			</div>
		</span>
	</div>
	<?php endif ?>
	<?php if ($columns > 1 && !$readonly): ?>
		<?php foreach ([
			// Later side handles own the overlapping bottom corners.
			'bottom' => __('field:rowspan'),
			'start' => __('field:indent'),
			'end' => __('field:colspan'),
		] as $edge => $title): ?>
			<span
				class="resize is-<?= $edge ?>"
				data-layout-resize="<?= $edge ?>"
				data-places="block"
				aria-hidden="true"
				title="<?= $this->escape($title) ?>">
				<?= \Cosray\Panel\Icon::render('grip-vertical') ?>
			</span>
		<?php endforeach ?>
		<?php // The line to the next part of a split, centred in the gap. ?>
		<span class="seam" data-places="columns" aria-hidden="true"></span>
		<span class="seam" data-places="rows" aria-hidden="true"></span>
	<?php endif ?>
	<div class="body" id="<?= $this->escape("{$rowId}-form") ?>">
		<div class="cms-fields">
			<?php $this->insert($editor ?? 'field/row-fields', [
				'type' => $blockType,
				'ownMeta' => false,
				'readonly' => $readonly,
				'labels' => $labels,
				'fieldsData' => $fieldsData,
				'rowName' => $rowName,
				'rowId' => $rowId,
			]) ?>
		</div>
	</div>
	<?php if ($settings): ?>
		<dialog class="cms-modal" data-size="compact" data-meta>
			<?php $this->insert('component/modal-header', ['title' => $label . ' — ' . __('field:block-settings')]) ?>
			<div class="modal-body cms-settings">
			<?php if ($columns > 1) {
				$this->insert('field/blocks/layout', [
					'layout' => $layout->array(),
					'columns' => $area->colspan ?? $columns,
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
			<?php foreach ($subMetas as $sub): ?>
				<?php $subName = (string) ($sub['name'] ?? ''); ?>
				<?php if ($labels): ?>
					<div class="cms-sub-label section"><?= $this->escape((string) ($sub['label'] ?? $subName)) ?></div>
				<?php endif ?>
				<?php $this->insert('field/meta', [
					'field' => $sub,
					'control' => $sub['metaControl'],
					'meta' => $fieldsData[$subName]['meta'] ?? null,
					'id' => "{$rowId}-{$subName}-meta",
					'nameRoot' => "{$rowName}[fields][{$subName}]",
				]) ?>
			<?php endforeach ?>
			<?php foreach ($slots as $sub): ?>
				<?php $subName = (string) ($sub['name'] ?? ''); ?>
				<?php if ($labels): ?>
					<div class="cms-sub-label section"><?= $this->escape((string) ($sub['label'] ?? $subName)) ?></div>
				<?php endif ?>
				<div class="cms-settings-slot" data-settings-slot="<?= $this->escape($subName) ?>"></div>
			<?php endforeach ?>
			</div>
		</dialog>
	<?php endif ?>
</div>
