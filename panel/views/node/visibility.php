<?php

use function Cosray\escape;

// The online and listing switches. They belong to the editor's visibility
// form, outside the editor form, and act at once; the response renders them
// again, out of band, with the state the node now has. data-editor-state
// keeps them from marking the editor as edited.
// Receives: visibility (published, hidden, released), oob.

$visibility = (array) u($visibility);
$oob = (bool) (u($oob ?? null) ?? false);
$published = (bool) ($visibility['published'] ?? false);
$hidden = (bool) ($visibility['hidden'] ?? false);
$released = (bool) ($visibility['released'] ?? false);
?>
<div id="editor-visibility" class="visibility" <?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<label class="toggle">
		<span class="copy">
			<span class="title"><?= escape(__('editor:online-label')) ?></span>
			<span class="help"><?= escape(__($released ? 'editor:online-help' : 'editor:online-unreleased')) ?></span>
		</span>
		<input
			id="editor-published-switch"
			type="checkbox"
			class="cms-switch"
			form="node-editor-visibility"
			name="online"
			value="1"
			data-editor-state
			<?= $published ? 'checked' : '' ?>
			<?= $released ? '' : 'disabled' ?> />
	</label>
	<label class="toggle">
		<span class="copy">
			<span class="title"><?= escape(__('editor:listed-label')) ?></span>
			<span class="help"><?= escape(__('editor:listed-help')) ?></span>
		</span>
		<input
			type="checkbox"
			class="cms-switch"
			form="node-editor-visibility"
			name="listed"
			value="1"
			data-editor-state
			<?= $hidden ? '' : 'checked' ?> />
	</label>
</div>
