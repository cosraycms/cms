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
		<div class="scroll">
			<?php $this->insert('component/rail-nav') ?>
		</div>
	<?php else: ?>
		<nav class="scroll" aria-label="<?= escape(__('panel:navigation')) ?>">
			<?php $this->insert('component/rail-nav') ?>
		</nav>
	<?php endif ?>
</aside>
