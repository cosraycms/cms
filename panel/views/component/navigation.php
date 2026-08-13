<?php

use function Cosray\escape;

if ((string) ($area ?? '') !== 'content' || count($collections) === 0) {
	return;
}
?>
<aside class="cms-sidebar">
	<nav class="scroll" aria-label="<?= escape(__('panel:navigation')) ?>">
		<?php $this->insert('component/collections', ['level' => 0]) ?>
	</nav>
</aside>
