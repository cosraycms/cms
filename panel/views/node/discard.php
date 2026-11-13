<?php

use function Cosray\escape;

// The questions before discarding the draft or the user's edits, asked at
// the button that discards. Discarding the draft can keep the user's edits,
// which build on the draft and still hold its changes; status.js offers that
// only while there are some. Rendered with the editor and again, out of band,
// after every save and switch.
// Receives: status (the editor's status view), oob.

$status = (array) u($status);
$oob = (bool) (u($oob ?? null) ?? false);
$stages = array_column((array) ($status['stages'] ?? []), null, 'key');
$draft = $stages['draft'] ?? null;
?>
<div id="editor-discard" class="discard" <?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<?php if (($status['discardDraft'] ?? false) && is_array($draft)): ?>
		<div
			id="editor-discard-draft"
			class="cms-popconfirm"
			popover="auto"
			role="dialog"
			aria-labelledby="editor-discard-draft-title"
			data-confirm>
			<h2 id="editor-discard-draft-title" class="title"><?= escape(__('editor:discard-confirm')) ?></h2>
			<p><?= escape(__('editor:discard-confirm-text', ['draft' => (string) $draft['text']])) ?></p>
			<label class="option" data-discard-mine <?= ($status['mine'] ?? 'none') === 'none' ? 'hidden' : '' ?>>
				<input type="checkbox" class="cms-checkbox" form="node-editor-discard" name="mine" value="discard" checked />
				<span class="copy">
					<span class="name"><?= escape(__('editor:discard-mine-too')) ?></span>
					<span class="help"><?= escape(__('editor:discard-mine-too-help')) ?></span>
				</span>
			</label>
			<div class="buttons">
				<button type="button" class="cms-button cms-button-secondary" data-confirm-cancel data-confirm-focus>
					<?= escape(__('editor:cancel')) ?>
				</button>
				<button type="submit" class="cms-button cms-button-danger cms-button-solid" form="node-editor-discard">
					<?= escape(__('editor:discard-draft')) ?>
				</button>
			</div>
		</div>
	<?php endif ?>
	<?php if ($status['autosave'] ?? false): ?>
		<div
			id="editor-discard-mine"
			class="cms-popconfirm"
			popover="auto"
			role="dialog"
			aria-labelledby="editor-discard-mine-title"
			data-confirm>
			<h2 id="editor-discard-mine-title" class="title"><?= escape(__('editor:mine-discard-confirm')) ?></h2>
			<p><?= escape(__('editor:mine-discard-confirm-text')) ?></p>
			<div class="buttons">
				<button type="button" class="cms-button cms-button-secondary" data-confirm-cancel data-confirm-focus>
					<?= escape(__('editor:cancel')) ?>
				</button>
				<button type="submit" class="cms-button cms-button-danger cms-button-solid" form="editor-autosave-discard">
					<?= escape(__('editor:mine-discard')) ?>
				</button>
			</div>
		</div>
	<?php endif ?>
</div>
