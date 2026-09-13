<?php

use function Cosray\escape;

// The line under the published switch that says the node has a working
// copy. Rendered with the inspector and again, out of band, by every
// successful save. Receives: draft (the working copy's facts, or null), oob.

$draft = $this->unwrap($draft ?? null);
$oob = (bool) ($this->unwrap($oob ?? null) ?? false);
$since = is_array($draft) ? (string) ($draft['since'] ?? '') : '';
$editor = is_array($draft) ? (string) ($draft['editor'] ?? '') : '';
?>
<p
	id="editor-changes-note"
	class="changes"
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>
	<?= is_array($draft) ? '' : 'hidden' ?>>
	<?php if (is_array($draft)): ?>
		<span class="title">
			<?= escape(__('editor:changes-since', ['date' => $since])) ?>
			<?php if ($editor !== ''): ?>
				<?= escape(__('editor:changes-by', ['editor' => $editor])) ?>
			<?php endif ?>
		</span>
		<span class="help"><?= escape(__('editor:changes-help')) ?></span>
	<?php endif ?>
</p>
