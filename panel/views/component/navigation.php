<?php

use function Cosray\escape;

// The rail carries collections only; dashboard and media moved up to the
// masthead and bring their own screens. It therefore belongs to the content
// area alone — on the others the frame runs single-column rather than offering
// navigation that leads out of the area you are in.
//
// With no collections there is nothing to put in it either.

if ((string) ($area ?? '') !== 'content' || count($collections) === 0) {
	return;
}
?>
<aside class="cms-sidebar">
	<nav class="scroll" aria-label="<?= escape(__('panel:navigation')) ?>">
		<?php $this->insert('component/collections', ['level' => 0]) ?>
	</nav>
</aside>
