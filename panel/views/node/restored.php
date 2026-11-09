<?php

use function Cosray\escape;

// Above the fields of an editor restored from an autosave: whose edits it
// shows and the way back to the stored state. The autosave behavior removes
// it, and the marks on the changed fields, once a save took the edits.
// Receives: restored (the autosave state's restored facts).

$restored = (array) u($restored);
?>
<div id="editor-restored" class="restored" role="status">
	<div class="copy">
		<p class="title"><?= escape(__('editor:restored', ['time' => (string) $restored['time']])) ?></p>
		<p class="help">
			<?= escape(__('editor:restored-help')) ?>
			<?php if ($restored['stale'] ?? false): ?>
				<?= escape(__('editor:restored-stale')) ?>
			<?php endif ?>
		</p>
	</div>
	<button type="submit" class="cms-button secondary small" form="editor-autosave-discard">
		<?= escape(__('editor:restored-discard')) ?>
	</button>
</div>
