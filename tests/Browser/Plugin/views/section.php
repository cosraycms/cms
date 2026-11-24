<?php

use function Cosray\escape;

$form = (string) u($form);
?>
<p class="cms-kit-inspector-help" data-state><?= u($online) ? 'online' : 'offline' ?></p>
<p class="cms-kit-inspector-help" data-note><?= escape((string) u($note)) ?></p>
<div class="cms-kit-inspector-row">
	<input class="cms-input" type="text" name="note" aria-label="Note" form="<?= escape($form) ?>" />
	<button type="submit" class="cms-button cms-button-secondary cms-button-small" form="<?= escape($form) ?>">Note</button>
</div>
