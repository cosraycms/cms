<?php

use function Cosray\escape;

// The system rail: the area's entries, so every system screen renders it.
// Navigating inside the area leaves the rail in place, which is why it
// patches itself in out of band to move the active mark.

$oob = (bool) ($oob ?? false);

if (!$rail) {
	return;
}

$entries = (array) u($systemNav);
?>
<div id="system-nav"<?= $oob ? ' hx-swap-oob="true"' : '' ?>>
	<ul class="cms-sidebar-list">
		<?php foreach ($entries as $entry): ?>
			<li class="cms-sidebar-item">
				<a
					class="cms-sidebar-link"
					href="<?= escape((string) $entry['url']) ?>"
					<?= $entry['active'] ? 'aria-current="page"' : '' ?>>
					<span class="cms-sidebar-label">
						<span class="cms-sidebar-icon"><?= \Cosray\Panel\Icon::render((string) $entry['icon']) ?></span>
						<span><?= escape((string) $entry['label']) ?></span>
					</span>
				</a>
			</li>
		<?php endforeach ?>
	</ul>
</div>
