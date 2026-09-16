<?php

use function Cosray\escape;

// The content-language selector of a screen: one per data-content-locale-scope,
// which the content-locales behavior binds. A radiogroup up to three locales, a select
// beyond. Receives: locales, selected, and optionally controlId, labelled
// (false renders the bare control with an accessible name, for a header) and
// strip (a bare vertical radiogroup of locale ids, for the collapsed inspector).

$locales = (array) $this->unwrap($locales);
$selected = (string) $selected;
$controlId = (string) ($this->unwrap($controlId ?? null) ?? 'cms-content-locale');
$strip = (bool) ($this->unwrap($strip ?? null) ?? false);
$labelled = !$strip && (bool) ($this->unwrap($labelled ?? null) ?? true);
$labelId = $controlId . '-label';
$title = __('editor:content-language');
$radios = $strip || count($locales) < 4;
$modifier = match (true) {
	$strip => ' is-vertical',
	$labelled => '',
	default => ' is-compact',
};

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
		class="cms-content-locales<?= $modifier ?>"
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
				<?= $strip ? 'title="' . escape($locale['title']) . '" aria-label="' . escape($locale['title']) . '"' : '' ?>
				data-content-locale-option="<?= escape($locale['id']) ?>">
				<?= escape($strip ? $locale['id'] : $locale['title']) ?>
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
