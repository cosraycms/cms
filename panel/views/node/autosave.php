<?php

use function Cosray\escape;

// The editor's autosave state for its behavior: where to send the edits,
// the stored state they start from and, when the user has an autosave
// the editor does not show, how to restore it. A save replaces it out of
// band with the base it wrote.
$autosave = (array) u($autosave);
$pending = is_array($autosave['pending'] ?? null) ? $autosave['pending'] : null;
$oob = (bool) ($oob ?? false);

?>
<div
	id="editor-autosave"
	hidden
	data-url="<?= escape((string) $autosave['url']) ?>"
	data-base="<?= escape((string) $autosave['base']) ?>"
	<?= ($autosave['restored'] ?? false) ? 'data-restored' : '' ?>
	<?php if ($pending !== null): ?>
		data-restore-url="<?= escape((string) $pending['url']) ?>"
		data-restore-time="<?= escape((string) $pending['time']) ?>"
		<?= $pending['stale'] ? 'data-restore-stale' : '' ?>
	<?php endif ?>
	<?= $oob ? 'hx-swap-oob="true" data-saved' : '' ?>></div>
