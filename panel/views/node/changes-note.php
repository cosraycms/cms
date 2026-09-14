<?php

use function Cosray\escape;

// The block under the published switch that says the node has a working
// copy and offers the two ways out of it. Rendered with the inspector and
// again, out of band, by every successful save. Receives: draft (the
// working copy's facts, or null), oob.

$draft = $this->unwrap($draft ?? null);
$oob = (bool) ($this->unwrap($oob ?? null) ?? false);
$since = is_array($draft) ? (string) ($draft['since'] ?? '') : '';
$editor = is_array($draft) ? (string) ($draft['editor'] ?? '') : '';
?>
<div
	id="editor-changes-note"
	class="changes"
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>
	<?= is_array($draft) ? '' : 'hidden' ?>>
	<?php if (is_array($draft)): ?>
		<p class="title">
			<?= escape(__('editor:changes-since', ['date' => $since])) ?>
			<?php if ($editor !== ''): ?>
				<?= escape(__('editor:changes-by', ['editor' => $editor])) ?>
			<?php endif ?>
		</p>
		<p class="help"><?= escape(__('editor:changes-help')) ?></p>
		<?php // The same two actions as the save menu, next to the switch whose

		// "published" state they explain. The discard button submits the
		// editor's discard form, the publish button the editor form itself. ?>
		<div class="actions">
			<button type="submit" class="cms-button secondary small" form="node-editor-discard">
				<?= escape(__('editor:changes-discard')) ?>
			</button>
			<button
				type="submit"
				class="cms-button primary small"
				form="node-editor-form"
				name="publish"
				value="1"
				data-editor-submit>
				<?= escape(__('editor:changes-publish')) ?>
			</button>
		</div>
	<?php endif ?>
</div>
