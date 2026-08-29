<?php

use function Cosray\escape;

// Creating a menu. An existing menu is edited inline on its tree screen.

$this->layout('layer/main');

$errors = (array) $this->unwrap($errors);
$action = (string) $action;
$backUrl = (string) $backUrl;
$handle = (string) $handle;
$description = (string) $description;
?>

<div class="page cms-menus">
	<header class="head">
		<div class="line">
			<h1><?= escape(__('menu:create-title')) ?></h1>
		</div>
	</header>

	<div class="body">
		<div class="card form-card">
			<form method="post" action="<?= escape($action) ?>">
				<?php if (count($errors) > 0): ?>
					<div class="cms-notice is-error" role="alert">
						<?php foreach ($errors as $error): ?>
							<p><?= escape((string) $error) ?></p>
						<?php endforeach ?>
					</div>
				<?php endif ?>

				<div class="field">
					<label for="menu-handle"><?= escape(__('menu:handle')) ?></label>
					<input
						class="cms-input"
						id="menu-handle"
						name="menu"
						type="text"
						required
						pattern="[a-z0-9-]{1,32}"
						value="<?= escape($handle) ?>"
						<?= isset($errors['menu']) ? 'aria-invalid="true"' : '' ?> />
					<p class="help"><?= escape(__('menu:handle-help')) ?></p>
				</div>

				<div class="field">
					<label for="menu-description"><?= escape(__('menu:description')) ?></label>
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

				<footer class="form-actions">
					<a class="cms-button secondary" href="<?= escape($backUrl) ?>"><?= escape(
						__('menu:cancel'),
					) ?></a>
					<button type="submit" class="cms-button primary"><?= escape(
						__('menu:create'),
					) ?></button>
				</footer>
			</form>
		</div>
	</div>
</div>
