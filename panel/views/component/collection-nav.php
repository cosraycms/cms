<?php

// Navigating inside the content region leaves the rail in place, so the tree
// patches itself in out of band to move the active mark. It sits inside the
// scroller rather than around it, which keeps the scroll position.

$oob = (bool) ($oob ?? false);

if (!$rail) {
	return;
}

?>
<div id="collection-nav"<?= $oob ? ' hx-swap-oob="true"' : '' ?>>
	<?php $this->insert('component/collections', ['level' => 0]) ?>
</div>
