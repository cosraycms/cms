<?php

use function Cosray\escape;

?>
<a
	class="logo"
	href="<?= $panelPath ?>"
	<?php // Leads to the dashboard, so it switches areas: see the area nav.?>
	hx-target="#frame"
	aria-label="<?= escape(__('nav:dashboard')) ?>">
	<?php if ($logo !== null): ?>
		<img class="image" src="<?= $logo ?>" alt="<?= escape(__('panel:logo')) ?>" />
	<?php else: ?>
		<span class="mark" aria-hidden="true">D</span>
		<span class="wordmark">Cosray</span>
	<?php endif ?>
</a>
