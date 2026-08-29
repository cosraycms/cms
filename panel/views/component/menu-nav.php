<?php

use function Cosray\escape;

// The menus rail: the area's listing, so every menu screen renders it.
// Navigating inside the area leaves the rail in place, which is why it
// patches itself in out of band to move the active mark.

$oob = (bool) ($oob ?? false);

if (!$rail) {
	return;
}

$menus = (array) $this->unwrap($menuNav);
$currentPath = (string) $this->unwrap($currentPath);
?>
<div id="menu-nav"<?= $oob ? ' hx-swap-oob="true"' : '' ?>>
	<ul class="list">
		<?php foreach ($menus as $entry): ?>
			<?php

			$url = (string) $entry['url'];
			// The edit form and every item URL live below the menu URL, so the
			// entry stays marked while the user works inside the menu.
			$active = $currentPath === $url || str_starts_with($currentPath, $url . '/');
			?>
			<li class="item">
				<a
					class="link"
					href="<?= escape($url) ?>"
					title="<?= escape((string) $entry['description']) ?>"
					<?= $active ? 'aria-current="page"' : '' ?>>
					<span class="label"><span><?= escape((string) $entry['menu']) ?></span></span>
					<span class="badge"><?= escape((string) $entry['items']) ?></span>
				</a>
			</li>
		<?php endforeach ?>
	</ul>

	<?php if ($manages): ?>
		<a class="create" href="<?= escape((string) $menuCreateUrl) ?>"><?= escape(
			__('menu:new'),
		) ?></a>
	<?php endif ?>
</div>
