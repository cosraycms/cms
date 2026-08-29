<?php

use function Cosray\escape;

// The menu's own fields, inline above its tree. Saving keeps the user in
// the menu, and a rejected handle comes back to this same bar with its
// error, so the edit screen this replaced is gone.

$props = (array) $this->unwrap($props);
$urls = (array) $this->unwrap($urls);

$errors = (array) $props['errors'];
$handle = (string) $props['handle'];
$description = (string) $props['description'];

// Without the permission the handle is read-only chrome: the field is
// disabled, so it never reaches the body, and the write ignores it anyway.
$manages = (bool) $manages;
?>
<div class="menu-props">
	<form method="post" action="<?= escape((string) $urls['edit']) ?>">
		<div class="cms-field<?= isset($errors['description']) ? ' has-error' : '' ?>">
			<label class="label" for="menu-description"><div><?= escape(
				__('menu:description'),
			) ?></div></label>
			<div class="control">
				<input
					class="cms-input"
					id="menu-description"
					name="description"
					type="text"
					required
					maxlength="128"
					value="<?= escape($description) ?>"
					<?= isset($errors['description']) ? 'aria-invalid="true"' : '' ?> />
			</div>
			<?php if (isset($errors['description'])): ?>
				<p class="error"><?= escape((string) $errors['description']) ?></p>
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
