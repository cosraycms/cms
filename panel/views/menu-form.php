<?php

use function Cosray\escape;

$this->layout('layer/main');

$errors = (array) $this->unwrap($errors);
$mode = (string) $mode;
$action = (string) $action;
$backUrl = (string) $backUrl;
$handle = (string) $handle;
$description = (string) $description;
$deleteUrl = $this->unwrap($deleteUrl);
$confirm = $this->unwrap($confirm);
?>

<div class="page cms-menus">
	<header class="head">
		<div class="line">
			<h1><?= escape($mode === 'create' ? __('menu:create-title') : __('menu:edit-title')) ?></h1>
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
					<?php if ($mode === 'edit'): ?>
						<p class="help warning"><?= escape(__('menu:rename-warning')) ?></p>
					<?php endif ?>
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
						$mode === 'create' ? __('menu:create') : __('menu:save'),
					) ?></button>
				</footer>
			</form>

			<?php if ($mode === 'edit' && is_string($deleteUrl)): ?>
				<?php // Its own form: a form cannot nest inside another. ?>
				<form
					class="form-danger"
					method="post"
					action="<?= escape($deleteUrl) ?>"
					hx-confirm="<?= escape((string) $confirm) ?>">
					<button type="submit" class="cms-button danger"><?= escape(
						__('menu:delete'),
					) ?></button>
				</form>
			<?php endif ?>
		</div>
	</div>
</div>
