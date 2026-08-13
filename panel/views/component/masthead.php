<?php

use function Cosray\escape;

// An area is current when the screen says so rather than when the URL matches:
// every collection and every node editor belongs to content, and they share no
// path with each other beyond the panel mount point.

$currentArea = (string) ($area ?? '');
$contentUrl = $this->unwrap($contentUrl ?? null);
// Cast before comparing: the template receives strings wrapped in a proxy, and
// `===` against one is false however equal the values are.
$localeId = (string) ($localeId ?? 'en');
$panelLocales = (array) ($this->unwrap($panelLocales ?? null) ?? []);

$areas = [['area' => 'dashboard', 'url' => (string) $panelPath, 'label' => __('nav:dashboard')]];

// The prefix is for after a boosted swap, where the panel script re-marks the
// nav from the URL alone and cannot ask which area rendered. A project's own
// panel pages sit outside it and lose the mark until the next full render.
if (is_string($contentUrl)) {
	$areas[] = [
		'area' => 'content',
		'url' => $contentUrl,
		'prefix' => (string) $panelPath . '/collection/',
		'label' => __('nav:content'),
	];
}

$areas[] = ['area' => 'media', 'url' => (string) $panelPath . '/media', 'label' => __('nav:media')];
?>
<header class="cms-masthead">
	<?php $this->insert('component/logo') ?>

	<nav class="areas" aria-label="<?= escape(__('panel:navigation')) ?>">
		<?php
		// The rail sits outside `#main`, so an area switch changes more of the
		// shell than a boosted swap can carry.
		?>
		<?php foreach ($areas as $entry): ?>
			<a
				class="area"
				data-nav
				href="<?= escape($entry['url']) ?>"
				hx-boost="false"
				<?= isset($entry['prefix'])
    	? 'data-nav-prefix="' . escape((string) $entry['prefix']) . '"'
    	: '' ?>
				<?= $entry['area'] === $currentArea ? 'aria-current="page"' : '' ?>>
				<?= escape($entry['label']) ?>
			</a>
		<?php endforeach ?>
	</nav>

	<div class="account">
		<?php if (count($panelLocales) > 1): ?>
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
