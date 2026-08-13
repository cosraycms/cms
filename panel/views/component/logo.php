<?php

use function Cosray\escape;

?>
<a
	class="logo"
	href="<?= $panelPath ?>"
	<?php // Leads to the dashboard, so it leaves the area: see the masthead.?>
	hx-boost="false"
	aria-label="<?= escape(__('nav:dashboard')) ?>">
	<?php if ($logo !== null): ?>
		<img class="image" src="<?= $logo ?>" alt="<?= escape(__('panel:logo')) ?>" />
	<?php else: ?>
		<span class="mark" aria-hidden="true">D</span>
		<span class="wordmark">Cosray</span>
	<?php endif ?>
</a>
