<?php

// The locale strip of a typed repeater row, switching every translated
// sub-field of the row at once. Rendered only where the row view marked
// itself as the scope, so the two agree through RowLocales::owned().

$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
?>
<span class="cms-locales">
	<?php foreach ($locales as $locale): ?>
		<button
			type="button"
			class="tab<?= $locale['id'] === $defaultLocale ? ' active' : '' ?>"
			data-locale-tab="<?= $this->escape($locale['id']) ?>">
			<?= $this->escape(strtoupper($locale['id'])) ?>
		</button>
	<?php endforeach ?>
</span>
