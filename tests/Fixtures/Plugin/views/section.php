<?php

use function Cosray\escape;

$form = (string) u($form);
$note = (string) u($note);
?>
<p class="cms-kit-inspector-help" data-test-online="<?= u($published) ? 'yes' : 'no' ?>" data-test-note><?= escape(
	$note,
) ?></p>
<div class="cms-kit-inspector-row">
	<input class="cms-input" type="text" name="note" aria-label="Note" form="<?= escape($form) ?>" />
	<button type="submit" class="cms-button cms-button-secondary cms-button-small" form="<?= escape($form) ?>">Note</button>
</div>
