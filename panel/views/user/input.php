<?php

use function Cosray\escape;

// One account input in the field wrapper the editor's error marks expect.

$name = (string) $name;
$label = (string) $label;
$type = (string) ($type ?? 'text');
$value = (string) ($value ?? '');
$required = (bool) ($required ?? false);
$help = u($help ?? null);
$autocomplete = (string) ($autocomplete ?? 'off');
$width = (int) ($width ?? 50);
?>

<div
	class="cms-field"
	style="grid-column: span <?= $width ?> / span <?= $width ?>"
	data-field="<?= escape($name) ?>">
	<label class="cms-field-label" for="user-<?= escape($name) ?>">
		<div>
			<span class="cms-field-name"><?= escape($label) ?></span>
			<?php if ($required): ?>
				<span class="cms-field-requirement"><?= escape(__('field:required')) ?></span>
			<?php endif ?>
		</div>
	</label>
	<div class="cms-field-body">
		<div class="cms-field-control">
			<input
				class="cms-input"
				id="user-<?= escape($name) ?>"
				name="<?= escape($name) ?>"
				type="<?= escape($type) ?>"
				value="<?= escape($value) ?>"
				autocomplete="<?= escape($autocomplete) ?>"
				<?= $required ? 'required' : '' ?> />
		</div>
		<?php if (is_string($help)): ?>
			<div class="cms-field-description"><?= escape($help) ?></div>
		<?php endif ?>
	</div>
</div>
