<?php

use function Cosray\escape;

// The save button's menu. Rendered with the editor and again, out of band,
// by every successful save: its entries follow the node's working copy.
// Receives: draft (the working copy's facts, or null), oob.

$draft = $this->unwrap($draft ?? null);
$hasDraft = is_array($draft);
$oob = (bool) ($this->unwrap($oob ?? null) ?? false);
?>
<div
	id="editor-save-options"
	class="cms-action-menu"
	popover="auto"
	data-action-menu
	data-align="end"
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<button type="submit" form="node-editor-form" name="publish" value="1" data-editor-submit>
		<?= \Cosray\Panel\Icon::render('floppy') ?>
		<?= escape($hasDraft ? __('editor:publish-changes') : __('editor:save-publish')) ?>
	</button>
	<?php if ($hasDraft): ?>
		<button type="submit" form="node-editor-discard" class="danger">
			<?= \Cosray\Panel\Icon::render('arrow-counterclockwise') ?>
			<?= escape(__('editor:discard-changes')) ?>
		</button>
	<?php endif ?>
</div>
