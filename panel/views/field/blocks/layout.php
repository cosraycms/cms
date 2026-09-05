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
$span = (int) ($layout['span'] ?? $min);
$indent = (int) ($layout['indent'] ?? 0);

$dimensions = [
	'span' => [__('field:span'), $span, $min, $columns - $indent],
	'rows' => [__('field:rows'), (int) ($layout['rows'] ?? 1), 1, Layout::MAX_ROWS],
	'indent' => [__('field:indent'), $indent, 0, $columns - $span],
];
?>
<div class="layout">
	<?php foreach ($dimensions as $dimension => [$label, $value, $low, $high]): ?>
		<div class="dimension">
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
