<?php

use function Cosray\escape;

// The system rail: the area's entries, so every system screen renders it.
// Navigating inside the area leaves the rail in place, which is why it
// patches itself in out of band to move the active mark.

$oob = (bool) ($oob ?? false);

if (!$rail) {
	return;
}

$entries = (array) $this->unwrap($systemNav);
?>
<div id="system-nav"<?= $oob ? ' hx-swap-oob="true"' : '' ?>>
	<ul class="list">
		<?php foreach ($entries as $entry): ?>
			<li class="item">
				<a
					class="link"
					href="<?= escape((string) $entry['url']) ?>"
					<?= $entry['active'] ? 'aria-current="page"' : '' ?>>
					<span class="label">
						<span class="icon"><?= \Cosray\Panel\Icon::render((string) $entry['icon']) ?></span>
						<span><?= escape((string) $entry['label']) ?></span>
					</span>
				</a>
			</li>
		<?php endforeach ?>
	</ul>
</div>
