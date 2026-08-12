<?php

use function Cosray\escape;

// Three regions: brand, the top-level areas, and the account controls. The
// design puts more in each of them than exists today — an area per section,
// an avatar menu — so the regions are the structure, not their contents.

$areas = [
	['url' => (string) $panelPath, 'label' => __('nav:dashboard')],
	['url' => (string) $panelPath . '/media', 'label' => __('nav:media')],
];
?>
<header class="cms-masthead">
	<?php $this->insert('component/logo') ?>

	<nav class="areas" aria-label="<?= escape(__('panel:navigation')) ?>">
		<?php foreach ($areas as $area): ?>
			<a
				class="area"
				data-nav
				href="<?= escape($area['url']) ?>"
				hx-target="#main"
				<?= (string) $currentPath === $area['url'] ? 'aria-current="page"' : '' ?>>
				<?= escape($area['label']) ?>
			</a>
		<?php endforeach ?>
	</nav>

	<div class="account">
		<?php if (count($panelLocales ?? []) > 1): ?>
			<form class="panel-locale" method="post" action="<?= $panelPath ?>/locale" hx-boost="false">
				<label>
					<span class="sr-only"><?= escape(__('nav:language')) ?></span>
					<select name="locale" onchange="this.form.submit()">
						<?php foreach ($panelLocales as $id => $title): ?>
							<option value="<?= escape($id) ?>"<?= $id === $localeId
	? ' selected'
	: '' ?>><?= escape($title) ?></option>
						<?php endforeach ?>
					</select>
				</label>
			</form>
		<?php endif ?>
		<form method="post" action="<?= $panelPath ?>/logout" hx-boost="false">
			<button class="action" type="submit"><?= escape(__('nav:logout')) ?></button>
		</form>
	</div>
</header>
