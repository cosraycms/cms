<?php

// The sections sites and plugins add to the inspector's status tab, below the
// node's status. Rendered with the editor and again, out of band, after every
// save and switch, so they follow the node's state.
// Receives: status (the editor's status view), oob.

$status = (array) u($status);
$oob = (bool) (u($oob ?? null) ?? false);
?>
<?php foreach ((array) ($status['sections'] ?? []) as $section): ?>
	<?php $this->include('kit/inspector-section', ['section' => $section, 'oob' => $oob]) ?>
<?php endforeach ?>
