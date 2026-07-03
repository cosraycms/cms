<?php

use function Cosray\escape;

// Fixed composition of sub-controls; value is a neutral-locale object
// keyed by sub-control key. Receives the group's value map in $value and
// the base input name (content[f][value][zxx]) in $name.

$control = (array) $this->unwrap($control);
$props = (array) ($control['props'] ?? []);
$subs = (array) ($props['fields'] ?? []);
$value = $this->unwrap($value ?? null);
$value = is_array($value) ? $value : [];

// Sub-controls carry their own options/attrs; the parent field's
// required/immutable flags do not cascade (parity with the editor).
$subField = ['required' => false, 'immutable' => false];
?>
<div class="cms-group">
	<?php foreach ($subs as $sub): ?>
		<?php

		$key = (string) ($sub['key'] ?? '');
		$subId = "{$id}-{$key}";
		$width = $sub['width'] ?? null;
		$sized = is_int($width) && $width > 0 && $width <= 100;
		?>
		<div
			class="cms-group-field"
			<?= $sized ? 'data-width style="--group-width: ' . $width . '%"' : '' ?>>
			<label class="cms-sub-label" for="<?= escape($subId) ?>">
				<?= escape((string) ($sub['label'] ?? $key)) ?>
			</label>
			<?php $this->insert('field/control', [
				'field' => $subField,
				'control' => (array) ($sub['control'] ?? []),
				'id' => $subId,
				'name' => "{$name}[{$key}]",
				'value' => $value[$key] ?? null,
				'data' => null,
			]) ?>
		</div>
	<?php endforeach ?>
</div>
