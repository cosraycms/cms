<?php

use function Cosray\escape;

if (!$rail) {
	return;
}

?>
<aside class="cms-sidebar" hx-target:inherited="#main">
	<nav class="scroll" aria-label="<?= escape(__('panel:navigation')) ?>">
		<?php $this->insert('component/rail-nav') ?>
	</nav>
</aside>
