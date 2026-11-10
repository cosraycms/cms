<?php

use function Cosray\escape;

// The editor's autosave state for its behavior: where to send the edits and
// the stored state they start from, which the save sends along so edits on
// an outdated state are refused. A save replaces it out of band with the
// base it wrote.
$autosave = (array) u($autosave);
$oob = (bool) ($oob ?? false);

?>
<div
	id="editor-autosave"
	hidden
	data-url="<?= escape((string) $autosave['url']) ?>"
	data-base="<?= escape((string) $autosave['base']) ?>"
	<?= is_array($autosave['restored'] ?? null) ? 'data-restored' : '' ?>
	<?= $oob ? 'hx-swap-oob="true" data-saved' : '' ?>>
	<input type="hidden" form="node-editor-form" name="_base" value="<?= escape((string) $autosave['base']) ?>" />
</div>
