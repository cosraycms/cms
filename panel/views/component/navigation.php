<?php

use function Cosray\escape;

if (!$rail) {
	return;
}

$filters = (string) $area === 'media';
?>
<aside
	class="cms-sidebar"
	hx-target:inherited="#main"
	<?= $filters ? 'aria-label="' . escape(__('common:filter')) . '"' : '' ?>>
	<?php // The media rail holds filters, which are not navigation. ?>
	<?php if ($filters): ?>
		<div class="cms-sidebar-scroll cms-scrollbar-quiet">
			<?php $this->include('component/rail-nav') ?>
		</div>
	<?php else: ?>
		<nav class="cms-sidebar-scroll cms-scrollbar-quiet" aria-label="<?= escape(__('panel:navigation')) ?>">
			<?php $this->include('component/rail-nav') ?>
		</nav>
	<?php endif ?>
</aside>
