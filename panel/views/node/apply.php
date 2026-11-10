<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The head's way into the main version, beside saving the draft. Applying
// is a menu without a default action, so its entries can say what applying
// does: right away visible to visitors, or still unpublished. An unpublished
// node with nothing to apply offers to publish its main version instead.
// status.js switches between the two as the user's edits come and go.
// Rendered with the editor and again, out of band, after every save and
// switch. Receives: status (the editor's status view), oob.

$status = (array) u($status);
$oob = (bool) (u($oob ?? null) ?? false);
$published = (bool) ($status['visibility']['published'] ?? false);
$pending = ($status['mine'] ?? 'none') !== 'none' || (bool) ($status['draft'] ?? false);
$conflict = ($status['mine'] ?? 'none') === 'conflict';
?>
<div
	id="editor-apply"
	class="apply"
	data-published="<?= $published ? 'true' : 'false' ?>"
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<?php if (!$published): ?>
		<button
			class="cms-button primary"
			type="submit"
			form="node-editor-visibility"
			name="publish"
			value="1"
			data-apply-when="idle"
			<?= $pending ? 'hidden' : '' ?>>
			<?= Icon::render('cloud-upload') ?>
			<?= escape(__('editor:publish')) ?>
		</button>
	<?php endif ?>
	<button
		class="cms-button primary"
		type="button"
		popovertarget="editor-apply-menu"
		aria-haspopup="menu"
		data-apply-when="pending"
		<?= !$pending && !$published ? 'hidden' : '' ?>
		<?= !$pending || $conflict ? 'disabled' : '' ?>>
		<?= Icon::render('cloud-upload') ?>
		<?= escape(__('editor:apply')) ?>
		<?= Icon::render('chevron-down') ?>
	</button>
	<div
		id="editor-apply-menu"
		class="cms-action-menu cms-apply-menu"
		popover="auto"
		data-action-menu
		data-align="end">
		<button type="submit" form="node-editor-form" name="apply" value="1" data-editor-submit>
			<span class="label"><?= escape(__('editor:apply-draft')) ?></span>
			<span class="help"><?= escape(__($published ? 'editor:apply-visible' : 'editor:apply-unpublished')) ?></span>
		</button>
		<?php if (!$published): ?>
			<button type="submit" form="node-editor-form" name="apply" value="publish" data-editor-submit>
				<span class="label"><?= escape(__('editor:apply-publish')) ?></span>
				<span class="help"><?= escape(__('editor:apply-publish-help')) ?></span>
			</button>
		<?php endif ?>
	</div>
</div>
