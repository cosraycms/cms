<?php

use function Cosray\escape;

?>
<a
	class="cms-masthead-logo"
	href="<?= $homeUrl ?>"
	<?php // Leads to the panel's start area, so it switches areas: see the area nav. ?>
	hx-target="#frame"
	<?php if ($dashboard): ?>
		aria-label="<?= escape(__('nav:dashboard')) ?>"
	<?php endif ?>>
	<?php if ($logo !== null): ?>
		<img src="<?= $logo ?>" alt="<?= escape(__('panel:logo')) ?>" />
	<?php else: ?>
		<span class="cms-masthead-mark" aria-hidden="true">D</span>
		<span class="cms-masthead-wordmark">Cosray</span>
	<?php endif ?>
</a>
