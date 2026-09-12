<?php

use function Cosray\escape;

// The content-language selector of a screen: one per data-content-locale-scope,
// which the tabs behavior binds. A radiogroup up to three locales, a select
// beyond. Receives: locales, selected, and optionally controlId and labelled
// (false renders the bare control with an accessible name, for a header).

$locales = (array) $this->unwrap($locales);
$selected = (string) $selected;
$controlId = (string) ($this->unwrap($controlId ?? null) ?? 'cms-content-locale');
$labelled = (bool) ($this->unwrap($labelled ?? null) ?? true);
$labelId = $controlId . '-label';
$title = __('editor:content-language');
$radios = count($locales) < 4;

if (count($locales) < 2) {
	return;
}
?>
<?php if ($labelled): ?>
	<div class="field">
		<?php if ($radios): ?>
			<span id="<?= escape($labelId) ?>" class="label"><?= escape($title) ?></span>
		<?php else: ?>
			<label class="label" for="<?= escape($controlId) ?>"><?= escape($title) ?></label>
		<?php endif ?>
<?php endif ?>
<?php if ($radios): ?>
	<div
		id="<?= escape($controlId) ?>"
		class="cms-content-locales<?= $labelled ? '' : ' is-compact' ?>"
		role="radiogroup"
		<?= $labelled ? 'aria-labelledby="' . escape($labelId) . '"' : 'aria-label="' . escape($title) . '"' ?>
		data-content-locale-control
		data-editor-state>
		<?php foreach ($locales as $locale): ?>
			<?php $active = $locale['id'] === $selected ?>
			<button
				type="button"
				class="option"
				role="radio"
				aria-checked="<?= $active ? 'true' : 'false' ?>"
				tabindex="<?= $active ? '0' : '-1' ?>"
				data-content-locale-option="<?= escape($locale['id']) ?>">
				<?= escape($locale['title']) ?>
			</button>
		<?php endforeach ?>
	</div>
<?php else: ?>
	<select
		id="<?= escape($controlId) ?>"
		class="cms-select"
		<?= $labelled ? '' : 'aria-label="' . escape($title) . '"' ?>
		data-content-locale-control
		data-content-locale-select
		data-editor-state>
		<?php foreach ($locales as $locale): ?>
			<option
				value="<?= escape($locale['id']) ?>"
				<?= $locale['id'] === $selected ? 'selected' : '' ?>>
				<?= escape($locale['title']) ?>
			</option>
		<?php endforeach ?>
	</select>
<?php endif ?>
<?php if ($labelled): ?>
	</div>
<?php endif ?>
