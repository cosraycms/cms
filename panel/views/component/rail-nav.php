<?php

// Which navigation the rail holds: the collection tree in the content
// area, the menus in theirs. Both the rail itself and the out-of-band
// patch after a content swap come through here.

$oob = (bool) ($oob ?? false);

$this->insert(
	(string) $area === 'menus' ? 'component/menu-nav' : 'component/collection-nav',
	['oob' => $oob],
);
