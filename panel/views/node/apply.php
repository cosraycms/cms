<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The head's way into the main version, beside saving the draft. Applying
// asks first, naming the stages it takes over and whether visitors see them
// at once; the step in the status tab opens the same question. An
// unpublished node can also be applied and published at once, and with
// nothing to apply offers to publish its main version instead. status.js
// switches between the two as the user's edits come and go and fills the
// question with the current stages. Rendered with the editor and again, out
// of band, after every save and switch.
// Receives: status (the editor's status view), oob.

$status = (array) u($status);
$oob = (bool) (u($oob ?? null) ?? false);
$published = (bool) ($status['visibility']['published'] ?? false);
$pending = ($status['mine'] ?? 'none') !== 'none' || (bool) ($status['draft'] ?? false);
$conflict = ($status['mine'] ?? 'none') === 'conflict';
$stages = array_column((array) ($status['stages'] ?? []), null, 'key');
$items = ['draft' => 'people', 'mine' => 'person'];
?>
<div
	id="editor-apply"
	class="apply"
	data-published="<?= $published ? 'true' : 'false' ?>"
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<?php if (!$published): ?>
		<button
			class="cms-button cms-button-primary"
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
	<div
		<?= $published ? '' : 'class="cms-split-button"' ?>
		data-apply-when="pending"
		<?= !$pending && !$published ? 'hidden' : '' ?>>
		<button
			class="cms-button cms-button-primary"
			type="button"
			popovertarget="editor-apply-confirm"
			data-apply-button
			<?= !$pending || $conflict ? 'disabled' : '' ?>>
			<?= Icon::render('arrow-bar-up') ?>
			<?= escape(__('editor:apply')) ?>
		</button>
		<?php if (!$published): ?>
			<button
				class="cms-button cms-button-primary toggle"
				type="button"
				popovertarget="editor-apply-menu"
				aria-haspopup="menu"
				aria-label="<?= escape(__('editor:apply-options')) ?>"
				data-apply-button
				<?= !$pending || $conflict ? 'disabled' : '' ?>>
				<?= Icon::render('chevron-down') ?>
			</button>
			<div
				id="editor-apply-menu"
				class="cms-action-menu cms-apply-menu"
				popover="auto"
				data-action-menu
				data-align="end">
				<button type="submit" form="node-editor-form" name="apply" value="publish" data-editor-submit>
					<span class="label"><?= escape(__('editor:apply-publish')) ?></span>
					<span class="help"><?= escape(__('editor:apply-publish-help')) ?></span>
				</button>
			</div>
		<?php endif ?>
	</div>
	<div
		id="editor-apply-confirm"
		class="cms-popconfirm"
		popover="auto"
		role="dialog"
		aria-labelledby="editor-apply-confirm-title"
		data-confirm
		data-align="end">
		<h2 id="editor-apply-confirm-title" class="title"><?= escape(__('editor:apply-confirm')) ?></h2>
		<p><?= escape(__('editor:apply-confirm-text')) ?></p>
		<ul class="stages">
			<?php foreach ($items as $key => $icon): ?>
				<?php $stage = $stages[$key] ?? null; ?>
				<?php if (is_array($stage)): ?>
					<li data-confirm-stage="<?= $key ?>" <?= $stage['state'] === 'none' ? 'hidden' : '' ?>>
						<span class="mark" data-stage="<?= $key ?>" data-state="<?= escape((string) $stage['state']) ?>">
							<?= Icon::render($icon) ?>
						</span>
						<span class="copy">
							<span class="name"><?= escape((string) $stage['name']) ?></span>
							<span class="help" data-stage-text><?= escape((string) $stage['text']) ?></span>
						</span>
					</li>
				<?php endif ?>
			<?php endforeach ?>
		</ul>
		<p class="note <?= $published ? 'is-online' : 'is-offline' ?>">
			<?= Icon::render('globe') ?>
			<span><?= escape(__($published ? 'editor:apply-online' : 'editor:apply-offline')) ?></span>
		</p>
		<div class="buttons">
			<button type="button" class="cms-button cms-button-secondary" data-confirm-cancel>
				<?= escape(__('editor:cancel')) ?>
			</button>
			<button
				type="submit"
				class="cms-button cms-button-primary"
				form="node-editor-form"
				name="apply"
				value="1"
				data-editor-submit
				data-confirm-focus>
				<?= escape(__('editor:apply')) ?>
			</button>
		</div>
	</div>
</div>
