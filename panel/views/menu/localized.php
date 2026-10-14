<?php

use function Cosray\escape;

// A per-locale text input group: every variant renders, the screen's
// content-language selector decides which one shows.

$name = (string) $name;
$label = (string) $label;
$values = (array) u($values);
$error = u($error ?? null);
$id = (string) $id;
$locales = (array) u($locales);
$defaultLocale = (string) $defaultLocale;
$help = u($help ?? null);
$helpSection = u($helpSection ?? null);
$helpHidden = (bool) ($helpHidden ?? false);
$section = u($section ?? null);
$sectionHide = u($sectionHide ?? null);
$sectionHidden = (bool) ($sectionHidden ?? false);
?>
<div
	class="cms-field<?= is_string($error) ? ' has-error' : '' ?>"
	<?= is_string($section) ? 'data-menu-section="' . escape($section) . '"' : '' ?>
	<?= is_string($sectionHide) ? 'data-menu-section-hide="' . escape($sectionHide) . '"' : '' ?>
	<?= $sectionHidden ? 'hidden' : '' ?>>
	<label
		class="label"
		for="<?= escape($id) ?>-<?= escape($defaultLocale) ?>"
		data-locale-label-for="<?= escape($id) ?>">
		<div><?= escape($label) ?></div>
	</label>
	<div class="control">
		<?php foreach ($locales as $locale): ?>
			<div
				class="variant"
				data-locale="<?= escape($locale['id']) ?>"
				<?= $locale['id'] === $defaultLocale ? '' : 'hidden' ?>>
				<input
					class="cms-input"
					id="<?= escape($id) ?>-<?= escape($locale['id']) ?>"
					name="<?= escape($name) ?>[<?= escape($locale['id']) ?>]"
					type="text"
					value="<?= escape((string) ($values[$locale['id']] ?? '')) ?>"
					<?= is_string($error) ? 'aria-invalid="true"' : '' ?> />
			</div>
		<?php endforeach ?>
	</div>
	<?php if (is_string($help)): ?>
		<p
			class="help"
			<?= is_string($helpSection) ? 'data-menu-section="' . escape($helpSection) . '"' : '' ?>
			<?= $helpHidden ? 'hidden' : '' ?>><?= escape($help) ?></p>
	<?php endif ?>
	<?php if (is_string($error)): ?>
		<p class="error"><?= escape($error) ?></p>
	<?php endif ?>
</div>
