<?php

use function Cosray\escape;

// The menu's own fields, inline above its tree. Saving keeps the user in
// the menu, and a rejected handle comes back to this same bar with its
// error, so the edit screen this replaced is gone.

$props = (array) $this->unwrap($props);
$urls = (array) $this->unwrap($urls);

$errors = (array) $props['errors'];
$handle = (string) $props['handle'];
$description = (array) $props['description'];
$locales = (array) $props['locales'];
$defaultLocale = (string) $props['defaultLocale'];
$maxDepth = $props['maxDepth'] === null ? null : (int) $props['maxDepth'];

// Without the permission the handle is read-only chrome: the field is
// disabled, so it never reaches the body, and the write ignores it anyway.
$manages = (bool) $manages;
?>
<div class="menu-props">
	<form method="post" action="<?= escape((string) $urls['edit']) ?>">
		<?php $this->insert('menu/localized', [
			'name' => 'description',
			'label' => __('menu:description'),
			'values' => $description,
			'error' => $errors['description'] ?? null,
			'id' => 'menu-description',
			'locales' => $locales,
			'defaultLocale' => $defaultLocale,
		]) ?>

		<div class="cms-field<?= isset($errors['maxDepth']) ? ' has-error' : '' ?>">
			<label class="label" for="menu-max-depth"><div><?= escape(
				__('menu:max-depth'),
			) ?></div></label>
			<div class="control">
				<input
					class="cms-input"
					id="menu-max-depth"
					name="maxDepth"
					type="number"
					min="1"
					max="10"
					placeholder="∞"
					value="<?= escape($maxDepth === null ? '' : (string) $maxDepth) ?>"
					<?= isset($errors['maxDepth']) ? 'aria-invalid="true"' : '' ?> />
			</div>
			<?php if (isset($errors['maxDepth'])): ?>
				<p class="error"><?= escape((string) $errors['maxDepth']) ?></p>
			<?php endif ?>
		</div>

		<div class="cms-field<?= isset($errors['menu']) ? ' has-error' : '' ?>">
			<label class="label" for="menu-handle"><div><?= escape(__('menu:handle')) ?></div></label>
			<div class="control">
				<input
					class="cms-input"
					id="menu-handle"
					name="menu"
					type="text"
					required
					pattern="[a-z0-9-]{1,32}"
					value="<?= escape($handle) ?>"
					<?= $manages ? '' : 'disabled' ?>
					<?= isset($errors['menu']) ? 'aria-invalid="true"' : '' ?> />
			</div>
			<?php if (isset($errors['menu'])): ?>
				<p class="error"><?= escape((string) $errors['menu']) ?></p>
			<?php endif ?>
		</div>

		<button type="submit" class="cms-button secondary"><?= escape(__('menu:save')) ?></button>
	</form>

	<?php if ($manages): ?>
		<form
			method="post"
			action="<?= escape((string) $urls['delete']) ?>"
			hx-confirm="<?= escape((string) $props['confirm']) ?>">
			<button type="submit" class="cms-button danger"><?= escape(__('menu:delete')) ?></button>
		</form>
	<?php endif ?>

	<p class="help"><?= escape(__('menu:handle-help')) ?> <?= escape(
		$manages ? __('menu:rename-warning') : __('menu:handle-locked'),
	) ?></p>
</div>
