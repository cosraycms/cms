<?php

use function Cosray\escape;

// Above the fields while the user's edits started from an older state than
// the one someone saved meanwhile: the one banner of the node editor. Saving
// anyway overwrites that state; discarding the edits loads it.
// Receives: status (the editor's status view), oob.

$status = (array) u($status);
$oob = (bool) (u($oob ?? null) ?? false);
$conflict = is_array($status['conflict'] ?? null) ? $status['conflict'] : null;
?>
<div
	id="editor-conflict"
	class="conflict"
	<?= $conflict === null ? 'hidden' : 'role="alert"' ?>
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<?php if ($conflict !== null): ?>
		<div class="copy">
			<p class="title"><?= escape(__($conflict['draft'] ? 'editor:conflict-draft' : 'editor:conflict-entry')) ?></p>
			<p class="help"><?= escape(__('editor:conflict-help', [
				'editor' => $conflict['editor'] !== '' ? $conflict['editor'] : __('editor:conflict-someone'),
				'time' => $conflict['time'],
			])) ?></p>
		</div>
		<button type="submit" class="cms-button secondary small" form="editor-autosave-discard">
			<?= escape(__('editor:mine-discard')) ?>
		</button>
	<?php endif ?>
</div>
