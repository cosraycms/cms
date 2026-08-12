<?php

use function Cosray\escape;

// The rail carries collections only; dashboard and media moved up to the
// masthead. With no collections there is nothing to put in it, and the frame
// runs single-column rather than showing an empty rail.

if (count($collections) === 0) {
	return;
}
?>
<aside class="cms-sidebar">
	<nav class="scroll" aria-label="<?= escape(__('panel:navigation')) ?>">
		<?php $this->insert('component/collections', ['level' => 0]) ?>
	</nav>
</aside>
