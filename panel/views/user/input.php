<?php

use function Cosray\escape;

// One account input in the field wrapper the editor's error marks expect.

$name = (string) $name;
$label = (string) $label;
$type = (string) ($type ?? 'text');
$value = (string) ($value ?? '');
$required = (bool) ($required ?? false);
$help = $this->unwrap($help ?? null);
$autocomplete = (string) ($autocomplete ?? 'off');
$width = (int) ($width ?? 50);
?>

<div
	class="cms-field<?= $required ? ' required' : '' ?>"
	style="grid-column: span <?= $width ?> / span <?= $width ?>"
	data-field="<?= escape($name) ?>">
	<label class="label" for="user-<?= escape($name) ?>">
		<div>
			<?= escape($label) ?>
			<?php if ($required): ?>
				<span class="requirement">(<?= escape(__('field:required')) ?>)</span>
			<?php endif ?>
		</div>
	</label>
	<div class="field-body">
		<div class="control">
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
			<div class="description"><?= escape($help) ?></div>
		<?php endif ?>
	</div>
</div>
