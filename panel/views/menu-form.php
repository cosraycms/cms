<?php

use function Cosray\escape;

// Creating a menu. An existing menu is edited inline on its tree screen.

$this->layout('layer/main');

$errors = (array) u($errors);
$action = (string) $action;
$backUrl = (string) $backUrl;
$handle = (string) $handle;
$description = (array) u($description);
$locales = (array) u($locales);
$defaultLocale = (string) $defaultLocale;
$maxDepth = u($maxDepth);
$maxDepth = $maxDepth === null ? null : (int) $maxDepth;
?>

<div
	class="page cms-menus"
	data-content-locale-scope
	data-content-locale="<?= escape($defaultLocale) ?>">
	<header class="head">
		<div class="line">
			<h1><?= escape(__('menu:create-title')) ?></h1>
		</div>
		<?php $this->include('component/content-locales', [
			'locales' => $locales,
			'selected' => $defaultLocale,
			'controlId' => 'cms-menu-locale',
			'labelled' => false,
		]) ?>
	</header>

	<div class="body">
		<div class="card form-card">
			<form method="post" action="<?= escape($action) ?>">
				<?php $this->include('component/csrf') ?>
				<?php if (count($errors) > 0): ?>
					<div class="cms-notice is-error" role="alert">
						<?php foreach ($errors as $error): ?>
							<p><?= escape((string) $error) ?></p>
						<?php endforeach ?>
					</div>
				<?php endif ?>

				<div class="cms-field<?= isset($errors['menu']) ? ' has-error' : '' ?>">
					<label class="label" for="menu-handle"><div><?= escape(
						__('menu:handle'),
					) ?></div></label>
					<div class="control">
						<input
							class="cms-input"
							id="menu-handle"
							name="menu"
							type="text"
							required
							pattern="[a-z0-9-]{1,32}"
							value="<?= escape($handle) ?>"
							<?= isset($errors['menu']) ? 'aria-invalid="true"' : '' ?> />
					</div>
					<p class="help"><?= escape(__('menu:handle-help')) ?></p>
				</div>

				<?php $this->include('menu/localized', [
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
					<p class="help"><?= escape(__('menu:max-depth-help')) ?></p>
					<?php if (isset($errors['maxDepth'])): ?>
						<p class="error"><?= escape((string) $errors['maxDepth']) ?></p>
					<?php endif ?>
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
