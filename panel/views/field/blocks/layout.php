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

// A part of a split edits the one dimension its split lets it change.
$dimensions = [
	'colspan' => [__('field:colspan'), $colspan, $min, $columns - $indent, 'block columns'],
	'rowspan' => [__('field:rowspan'), (int) ($layout['rowspan'] ?? 1), 1, Layout::MAX_ROWSPAN, 'block rows'],
	'indent' => [__('field:indent'), $indent, 0, $columns - $colspan, 'block'],
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
