<?php

use Cosray\Block\Layout;

// The block's layout as numbers in its settings dialog. Unnamed on
// purpose: the row's hidden layout inputs are what the form submits,
// these mirror them and the blocks behavior keeps both in step. Each
// limit is the room the other dimensions leave.

$layout = (array) $this->unwrap($layout);
$columns = max(1, (int) $columns);
$min = min($columns, max(1, (int) $min));
$id = (string) $this->unwrap($id);
$colspan = (int) ($layout['colspan'] ?? $min);
$indent = (int) ($layout['indent'] ?? 0);
$col = (int) ($layout['col'] ?? 0);

// A part of a split edits the one dimension its split lets it change. A
// block on the grid is placed by its start column and row; the blocks
// behavior keeps the limits to the room its neighbours leave.
$dimensions = [
	'colspan' => [__('field:colspan'), $colspan, $min, $columns - max($indent, $col - 1), 'block columns'],
	'rowspan' => [__('field:rowspan'), (int) ($layout['rowspan'] ?? 1), 1, Layout::MAX_ROWSPAN, 'block rows'],
	'col' => [__('field:column'), $col, 1, $columns - $colspan + 1, 'block'],
	'row' => [__('field:row'), (int) ($layout['row'] ?? 0), 1, 999, 'block'],
];
?>
<div class="layout">
	<?php foreach ($dimensions as $dimension => [$label, $value, $low, $high, $places]): ?>
		<div class="dimension" data-places="<?= $places ?>">
			<label class="cms-sub-label" for="<?= $this->escape("{$id}-{$dimension}") ?>">
				<?= $this->escape($label) ?>
			</label>
			<input
				type="number"
				class="cms-input"
				id="<?= $this->escape("{$id}-{$dimension}") ?>"
				data-layout-input="<?= $dimension ?>"
				value="<?= $value ?>"
				min="<?= $low ?>"
				max="<?= $high ?>"
				step="1"
				inputmode="numeric" />
		</div>
	<?php endforeach ?>
</div>
