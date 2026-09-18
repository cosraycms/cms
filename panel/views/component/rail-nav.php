<?php

// What the rail holds: the collection tree in the content area, the menus in
// theirs, the system entries in theirs, the library's filters in the media
// area. Both the rail itself and the out-of-band patch after a content swap
// come through here.

$oob = (bool) ($oob ?? false);

$this->insert(match ((string) $area) {
	'media' => 'component/media-rail',
	'menus' => 'component/menu-nav',
	'system' => 'component/system-nav',
	default => 'component/collection-nav',
}, ['oob' => $oob]);
