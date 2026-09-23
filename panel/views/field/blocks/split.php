<?php

use Cosray\Block\Layout;

// A split: one block on the canvas whose parts are blocks laid out on
// its area, side by side or stacked. It carries a uid and its layout but
// no type, and holds its parts in a nested repeater that is its own row
// list — no templates, no footer: parts come from splits only, stamped
// from the field's templates. data-split names the direction as the
// renderer derives it; the split behavior sets it on a new split.

$field = (array) $this->unwrap($field);
$index = $this->unwrap($index);
$rowData = $this->unwrap($rowData ?? null);
$rowData = is_array($rowData) ? $rowData : null;
$blockTypes = (array) $this->unwrap($blockTypes);
$columns = max(1, (int) $columns);
$min = min($columns, max(1, (int) $min));
$metaControl = $this->unwrap($metaControl ?? null);
$metaControl = is_array($metaControl) ? $metaControl : null;
$readonly = (bool) ($this->unwrap($readonly ?? null) ?? false);
$name = (string) $this->unwrap($name);
$id = (string) $this->unwrap($id);

$rowName = "{$name}[{$index}]";
$rowId = "{$id}-{$index}";
$uid = is_string($rowData['uid'] ?? null) ? $rowData['uid'] : '';
$layout = Layout::normalize($rowData['layout'] ?? null, $columns, $min);
$blocks = array_values(array_filter(
	is_array($rowData['blocks'] ?? null) ? $rowData['blocks'] : [],
	is_array(...),
));
$first = Layout::normalize($blocks[0]['layout'] ?? null, $layout->colspan, $min, $layout->rowspan);
$direction = $blocks !== [] && $first->colspan === $layout->colspan ? 'rows' : 'columns';
$padding = $rowData['meta']['padding']['zxx'] ?? null;
$padding = in_array($padding, \Cosray\Field\Blocks::SPACING, true) ? (string) $padding : '';
$style = "--colspan: {$layout->colspan}; --rowspan: {$layout->rowspan}";
$style .= $layout->placed() ? "; --col: {$layout->col}; --row: {$layout->row}" : '';
$label = __('field:split-block');
?>
<div
	class="block is-split"
	data-repeater-row
	data-meta-owner
	data-split="<?= $direction ?>"
	<?= $layout->placed() ? 'data-placed' : '' ?>
	<?= $padding !== '' ? 'data-padding="' . $this->escape($padding) . '"' : '' ?>
	style="<?= $this->escape($style) ?>">
	<input
		type="hidden"
		data-repeater-uid
		name="<?= $this->escape("{$rowName}[uid]") ?>"
		value="<?= $this->escape($uid) ?>" />
	<?php foreach (['col' => 0, 'row' => 0, ...$layout->array()] as $dimension => $value): ?>
		<input
			type="hidden"
			name="<?= $this->escape("{$rowName}[layout][{$dimension}]") ?>"
			value="<?= $value ?>"
			data-layout="<?= $dimension ?>" />
	<?php endforeach ?>
	<?php if (!$readonly): ?>
	<div class="chrome">
		<span class="tools">
			<span
				class="grip"
				data-repeater-grip
				tabindex="0"
				title="<?= $this->escape(__('field:drag-block')) ?>"
				aria-label="<?= $this->escape(__('field:drag-block') . '. ' . __('field:resize-block')) ?>"
				aria-keyshortcuts="Alt+ArrowLeft Alt+ArrowRight Alt+ArrowUp Alt+ArrowDown Alt+Shift+ArrowLeft Alt+Shift+ArrowRight">
				<?= \Cosray\Panel\Icon::render('grip-vertical') ?>
			</span>
			<button
				type="button"
				class="gear"
				data-meta-open
				aria-label="<?= $this->escape(__('field:block-settings')) ?>"
				title="<?= $this->escape(__('field:block-settings')) ?>">
				<?= \Cosray\Panel\Icon::render('pencil') ?>
			</button>
			<button type="button" class="kebab"
				popovertarget="<?= $this->escape("{$rowId}-actions") ?>"
				aria-haspopup="menu" aria-label="<?= $this->escape(__('field:block-actions')) ?>">
				<?= \Cosray\Panel\Icon::render('three-dots-vertical') ?>
			</button>
			<div id="<?= $this->escape("{$rowId}-actions") ?>" class="cms-action-menu"
				popover="auto" data-action-menu data-align="end">
					<button type="button" data-repeater-move="up">
						<?= $this->escape(__('common:move-up')) ?>
					</button>
					<button type="button" data-repeater-move="down">
						<?= $this->escape(__('common:move-down')) ?>
					</button>
					<button type="button" class="danger" data-repeater-remove>
						<?= $this->escape(__('field:remove-block')) ?>
					</button>
			</div>
		</span>
	</div>
	<?php foreach ([
		'bottom' => __('field:rowspan'),
		'start' => __('field:column'),
		'end' => __('field:colspan'),
	] as $edge => $title): ?>
		<span
			class="resize is-<?= $edge ?>"
			data-layout-resize="<?= $edge ?>"
			aria-hidden="true"
			title="<?= $this->escape($title) ?>">
			<?= \Cosray\Panel\Icon::render('grip-vertical') ?>
		</span>
	<?php endforeach ?>
	<?php endif ?>
	<div
		class="parts"
		data-repeater
		data-repeater-list
		data-name="<?= $this->escape("{$rowName}[blocks]") ?>"
		data-id="<?= $this->escape("{$rowId}-blocks") ?>"
		data-columns="<?= $layout->colspan ?>"
		data-min="<?= $min ?>">
		<?php foreach ($blocks as $position => $block) {
			$type = $block['type'] ?? null;

			if (!is_string($type) || !isset($blockTypes[$type])) {
				echo '<div class="cms-control-unknown">';
				echo $this->escape(__('field:unknown-block-type', ['type' => (string) $type]));
				echo '</div>';

				continue;
			}

			$this->insert('field/blocks/row', [
				'index' => $position,
				'rowData' => $block,
				'blockType' => $blockTypes[$type],
				'area' => $layout,
				'name' => "{$rowName}[blocks]",
				'id' => "{$rowId}-blocks",
			]);
		} ?>
	</div>
	<dialog class="cms-modal" data-size="compact" data-meta>
		<?php $this->insert('component/modal-header', ['title' => $label . ' — ' . __('field:block-settings')]) ?>
		<div class="modal-body cms-settings">
			<?php $this->insert('field/blocks/layout', [
				'layout' => $layout->array(),
				'columns' => $columns,
				'min' => $min,
				'id' => "{$rowId}-layout",
			]) ?>
			<?php if ($metaControl !== null) {
				$this->insert('field/meta', [
					'field' => $field,
					'control' => $metaControl,
					'meta' => $rowData['meta'] ?? null,
					'id' => "{$rowId}-meta",
					'nameRoot' => $rowName,
				]);
			} ?>
		</div>
	</dialog>
</div>
