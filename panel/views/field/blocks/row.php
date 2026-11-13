<?php

use Cosray\Block\Layout;

// One block, on the field's grid or as a part of a split: the same
// markup serves both, since a split moves blocks between the two. What
// differs by place is marked with data-places — `block` on the grid,
// `columns` or `rows` in a split of that direction — and shown by the
// editor styles, so a block moved in or out needs no new markup.

$field = (array) u($field);
$index = u($index);
$rowData = u($rowData ?? null);
$rowData = is_array($rowData) ? $rowData : null;
$blockType = (array) u($blockType);
$blockTypes = (array) u($blockTypes);
$columns = max(1, (int) $columns);
$min = min($columns, max(1, (int) $min));
$metaControl = u($metaControl ?? null);
$metaControl = is_array($metaControl) ? $metaControl : null;
$name = (string) u($name);
$id = (string) u($id);

// The split a part sits in, which bounds its layout.
$area = u($area ?? null);
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
$readonly = (bool) (u($readonly ?? null) ?? false);
$padding = $rowData['meta']['padding']['zxx'] ?? null;
$padding = in_array($padding, \Cosray\Field\Blocks::SPACING, true) ? (string) $padding : '';
$style = "--colspan: {$layout->colspan}; --rowspan: {$layout->rowspan}";
$style .= $layout->placed() ? "; --col: {$layout->col}; --row: {$layout->row}" : '';
$labels = (bool) ($blockType['labels'] ?? true);
// A type with an editor view of its own (#[Editor]) renders as content
// whatever its field count; the generic form keeps its labels.
$editor = is_string($blockType['editor'] ?? null) ? $blockType['editor'] : null;
$bare = !$labels || $editor !== null;
// Visible sub-fields marked #[Settings] are edited in the block's dialog,
// the others on the canvas.
$canvas = [];
$settingsSubs = [];

foreach ((array) ($blockType['fields'] ?? []) as $sub) {
	if (is_array($sub) && is_string($sub['name'] ?? null) && !($sub['hidden'] ?? false)) {
		if ($sub['settings'] ?? false) {
			$settingsSubs[$sub['name']] = $sub;
		} else {
			$canvas[$sub['name']] = $sub;
		}
	}
}
// Sub-fields with a meta group of their own edit it in the block's dialog.
$subMetas = array_values(array_filter(
	(array) ($blockType['fields'] ?? []),
	static fn(mixed $sub): bool => (
		is_array($sub)
		&& is_array($sub['metaControl'] ?? null)
		&& !($sub['hidden'] ?? false)
	),
));
// An element sub-field on the canvas gets a slot in the dialog for the
// controls it keeps out of the content; the host hands the slot to the
// element. One in the dialog already shows them all.
$slots = array_values(array_filter(
	(array) ($blockType['fields'] ?? []),
	static fn(mixed $sub): bool => (
		is_array($sub)
		&& is_array($sub['control'] ?? null)
		&& ($sub['control']['name'] ?? null) === 'element'
		&& !($sub['hidden'] ?? false)
		&& !($sub['settings'] ?? false)
	),
));
// An editor view follows the block's scalar neutral values without a script
// of its own: the body carries each as data-value-{sub} and --value-{sub},
// and the block-values behavior keeps them current.
$reflected = [];
$bodyAttributes = '';
$bodyStyle = [];

if ($editor !== null) {
	foreach ([...$canvas, ...$settingsSubs] as $subName => $sub) {
		$control = $sub['control']['name'] ?? null;

		if (
			!in_array($control, ['option', 'color', 'checkbox', 'number', 'text'], true)
			|| ($sub['translate'] ?? false)
		) {
			continue;
		}

		$stored = $fieldsData[$subName]['value']['zxx'] ?? null;
		$value = match (true) {
			is_bool($stored) => $stored ? 'true' : 'false',
			is_int($stored), is_float($stored), is_string($stored) => (string) $stored,
			default => $control === 'checkbox' ? 'false' : '',
		};
		$key = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $subName));
		$reflected[] = $subName;
		$bodyAttributes .= ' data-value-' . $key . '="' . $this->escape($value) . '"';

		// A value that could end the declaration stays out of the style.
		if ($value !== '' && preg_match('/[;{}<>"\'\\\\]/', $value) !== 1) {
			$bodyStyle[] = "--value-{$key}: {$value}";
		}
	}
}

$settings = $metaControl !== null || $columns > 1 || $subMetas !== [] || $slots !== [] || $settingsSubs !== [];
// Another type to change to replaces the block with a fresh one of it.
$retype = count($blockTypes) > 1;
?>
<div
	class="block<?= $bare ? ' is-bare' : '' ?>"
	data-repeater-row
	data-meta-owner
	<?= $layout->placed() ? 'data-placed' : '' ?>
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
		name="<?= $this->escape("{$rowName}[layout][col]") ?>"
		value="<?= $layout->col ?>"
		data-layout="col" />
	<input
		type="hidden"
		name="<?= $this->escape("{$rowName}[layout][row]") ?>"
		value="<?= $layout->row ?>"
		data-layout="row" />
	<?php if ($readonly && $settingsSubs !== []): ?>
	<div class="chrome">
		<span class="tools">
			<button
				type="button"
				class="gear"
				data-dialog-open="meta"
				aria-label="<?= $this->escape(__('field:block-settings')) ?>"
				title="<?= $this->escape(__('field:block-settings')) ?>">
				<?= \Cosray\Panel\Icon::render('pencil') ?>
			</button>
		</span>
	</div>
	<?php elseif (!$readonly): ?>
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
			<?php $this->include('field/blocks/inserter', [
				'commonChoices' => $commonChoices,
				'more' => $more,
				'id' => "{$rowId}-insert",
				'places' => $columns > 1 ? 'block' : null,
			]) ?>
			<?php if ($settings): ?>
				<button
					type="button"
					class="gear"
					data-dialog-open="meta"
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
					<?php if ($retype): ?>
						<button
							type="button"
							data-retype
							data-retype-confirm="<?= $this->escape(__('field:change-type-confirm')) ?>"
							data-confirm-action="<?= $this->escape(__('field:change-type')) ?>"
							data-confirm-tone="danger"
							data-places="block columns rows">
							<?= $this->escape(__('field:change-type')) ?>
						</button>
					<?php endif ?>
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
					<?php if ($retype): ?>
						<button
							type="button"
							data-retype
							data-retype-confirm="<?= $this->escape(__('field:change-type-confirm')) ?>"
							data-confirm-action="<?= $this->escape(__('field:change-type')) ?>"
							data-confirm-tone="danger">
							<?= $this->escape(__('field:change-type')) ?>
						</button>
					<?php endif ?>
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
			// Later side handles own the overlapping corners. The top edge
			// shows only where it can move (data-top-edge, set by placement).
			'top' => __('field:row'),
			'bottom' => __('field:rowspan'),
			'start' => __('field:column'),
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
		<?php // The line to the next part of a split, centred in the gap; side by side it drags. ?>
		<span
			class="seam"
			data-places="columns"
			data-layout-resize="end"
			aria-hidden="true"
			title="<?= $this->escape(__('field:colspan')) ?>">
			<span class="knob"></span>
		</span>
		<span class="seam" data-places="rows" aria-hidden="true"></span>
	<?php endif ?>
	<div
		class="body"
		id="<?= $this->escape("{$rowId}-form") ?>"
		<?php if ($reflected !== []): ?>
			data-block-values="<?= $this->escape(implode(' ', $reflected)) ?>"
			<?= $bodyAttributes ?>
			<?= $bodyStyle !== [] ? 'style="' . $this->escape(implode('; ', $bodyStyle)) . '"' : '' ?>
		<?php endif ?>>
		<div class="cms-fields">
			<?php if ($canvas === [] && $editor === null): ?>
				<?php // Every field sits in the dialog: the card names the block and opens it. ?>
				<button
					type="button"
					class="summary"
					data-dialog-open="meta"
					title="<?= $this->escape(__('field:block-settings')) ?>">
					<?= \Cosray\Panel\Icon::render('pencil') ?>
					<span><?= $this->escape($label) ?></span>
				</button>
			<?php elseif ($editor !== null): ?>
				<?php // Spans the field grid, so a view lays out its fields as it likes. ?>
				<div class="view">
					<?php $this->include($editor, [
						'type' => $blockType,
						'subs' => $canvas,
						'ownMeta' => false,
						'readonly' => $readonly,
						'labels' => $labels,
						'fieldsData' => $fieldsData,
						'rowName' => $rowName,
						'rowId' => $rowId,
					]) ?>
				</div>
			<?php else: ?>
				<?php $this->include('field/row-fields', [
					'type' => $blockType,
					'subs' => $canvas,
					'ownMeta' => false,
					'readonly' => $readonly,
					'labels' => $labels,
					'fieldsData' => $fieldsData,
					'rowName' => $rowName,
					'rowId' => $rowId,
				]) ?>
			<?php endif ?>
		</div>
	</div>
	<?php if ($settings): ?>
		<?php $this->component('component/dialog', ['name' => 'meta', 'size' => 'compact', 'title' => $label . ' — ' . __('field:block-settings')]) ?>
			<div class="cms-modal-body cms-settings">
			<?php if ($settingsSubs !== []): ?>
				<div class="cms-fields fields">
					<?php $this->include('field/row-fields', [
						'type' => $blockType,
						'subs' => $settingsSubs,
						'readonly' => $readonly,
						'labels' => true,
						'fieldsData' => $fieldsData,
						'rowName' => $rowName,
						'rowId' => $rowId,
					]) ?>
				</div>
				<?php if (!$readonly && ($columns > 1 || $metaControl !== null || $subMetas !== [] || $slots !== [])): ?>
					<hr class="rule" />
				<?php endif ?>
			<?php endif ?>
			<?php if (!$readonly && $columns > 1) {
				$this->include('field/blocks/layout', [
					'layout' => $layout->array(),
					'columns' => $area->colspan ?? $columns,
					'min' => $min,
					'id' => "{$rowId}-layout",
				]);
			} ?>
			<?php if (!$readonly && $metaControl !== null) {
				$this->include('field/meta', [
					'field' => $field,
					'control' => $metaControl,
					'meta' => $rowData['meta'] ?? null,
					'id' => "{$rowId}-meta",
					'nameRoot' => $rowName,
				]);
			} ?>
			<?php foreach ($readonly ? [] : $subMetas as $sub): ?>
				<?php $subName = (string) ($sub['name'] ?? ''); ?>
				<?php if ($labels): ?>
					<div class="cms-sub-label section"><?= $this->escape((string) ($sub['label'] ?? $subName)) ?></div>
				<?php endif ?>
				<?php $this->include('field/meta', [
					'field' => $sub,
					'control' => $sub['metaControl'],
					'meta' => $fieldsData[$subName]['meta'] ?? null,
					'id' => "{$rowId}-{$subName}-meta",
					'nameRoot' => "{$rowName}[fields][{$subName}]",
				]) ?>
			<?php endforeach ?>
			<?php foreach ($readonly ? [] : $slots as $sub): ?>
				<?php $subName = (string) ($sub['name'] ?? ''); ?>
				<?php if ($labels): ?>
					<div class="cms-sub-label section"><?= $this->escape((string) ($sub['label'] ?? $subName)) ?></div>
				<?php endif ?>
				<div class="cms-settings-slot" data-settings-slot="<?= $this->escape($subName) ?>"></div>
			<?php endforeach ?>
			</div>
		<?php $this->end() ?>
	<?php endif ?>
</div>
