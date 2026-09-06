<?php

use function Cosray\escape;

$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$controlId = (string) ($this->unwrap($controlId ?? null) ?? 'cms-content-locale');
$labelId = $controlId . '-label';
?>
<div class="field">
	<?php if (count($locales) < 4): ?>
		<span id="<?= escape($labelId) ?>" class="label">
			<?= escape(__('editor:content-language')) ?>
		</span>
		<div
			id="<?= escape($controlId) ?>"
			class="cms-content-locales"
			role="radiogroup"
			aria-labelledby="<?= escape($labelId) ?>"
			data-content-locale-control
			data-editor-state>
			<?php foreach ($locales as $locale): ?>
				<?php $active = $locale['id'] === $defaultLocale ?>
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
		<label class="label" for="<?= escape($controlId) ?>">
			<?= escape(__('editor:content-language')) ?>
		</label>
		<select
			id="<?= escape($controlId) ?>"
			class="cms-select"
			data-content-locale-control
			data-content-locale-select
			data-editor-state>
			<?php foreach ($locales as $locale): ?>
				<option
					value="<?= escape($locale['id']) ?>"
					<?= $locale['id'] === $defaultLocale ? 'selected' : '' ?>>
					<?= escape($locale['title']) ?>
				</option>
			<?php endforeach ?>
		</select>
	<?php endif ?>
</div>
