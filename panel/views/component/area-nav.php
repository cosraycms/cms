<?php

use function Cosray\escape;

// An area is current when the screen says so rather than when the URL matches:
// every collection and every node editor belongs to content, and they share no
// path with each other beyond the panel mount point.

$oob = (bool) ($oob ?? false);
$currentArea = (string) ($area ?? '');
$contentUrl = $this->unwrap($contentUrl ?? null);

$areas = [['area' => 'dashboard', 'url' => (string) $panelPath, 'label' => __('nav:dashboard')]];

if (is_string($contentUrl)) {
	$areas[] = ['area' => 'content', 'url' => $contentUrl, 'label' => __('nav:content')];
}

$areas[] = ['area' => 'media', 'url' => (string) $panelPath . '/media', 'label' => __('nav:media')];

$menusUrl = $this->unwrap($menusUrl ?? null);

if (is_string($menusUrl)) {
	$areas[] = ['area' => 'menus', 'url' => $menusUrl, 'label' => __('nav:menus')];
}

?>
<nav
	id="area-nav"
	class="areas"
	aria-label="<?= escape(__('panel:navigation')) ?>"
	hx-target:inherited="#frame"
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<?php foreach ($areas as $entry): ?>
		<a
			class="area"
			href="<?= escape($entry['url']) ?>"
			<?= $entry['area'] === $currentArea ? 'aria-current="page"' : '' ?>>
			<?= escape($entry['label']) ?>
		</a>
	<?php endforeach ?>
</nav>
