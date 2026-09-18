<?php

// The library's filters are its own state, so the rail is only a mount point:
// the media island renders into it and takes its content away with it.

$oob = (bool) ($oob ?? false);

if (!$rail) {
	return;
}

?>
<div id="media-rail"<?= $oob ? ' hx-swap-oob="true"' : '' ?> data-media-rail></div>
