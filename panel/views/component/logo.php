<?php

use function Cosray\escape;

?>
<a
	class="logo"
	href="<?= $homeUrl ?>"
	<?php // Leads to the panel's start area, so it switches areas: see the area nav. ?>
	hx-target="#frame"
	<?php if ($dashboard): ?>
		aria-label="<?= escape(__('nav:dashboard')) ?>"
	<?php endif ?>>
	<?php if ($logo !== null): ?>
		<img class="image" src="<?= $logo ?>" alt="<?= escape(__('panel:logo')) ?>" />
	<?php else: ?>
		<span class="mark" aria-hidden="true">D</span>
		<span class="wordmark">Cosray</span>
	<?php endif ?>
</a>
