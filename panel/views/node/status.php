<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The inspector's status tab: the node's stages top to bottom, from
// Panel\NodeStatus, each with what it means and what can be done there. The
// publication switch sits in the main version's stage and the listing switch
// below the stages; both belong to the editor's visibility form, outside the editor
// form, and act at once. data-editor-state keeps them from marking the
// editor as edited. Rendered with the editor and again, out of band, after
// every save and switch.
// Receives: status (the editor's status view), oob.

$status = (array) u($status);
$oob = (bool) (u($oob ?? null) ?? false);
$stages = (array) ($status['stages'] ?? []);
$visibility = is_array($status['visibility'] ?? null) ? $status['visibility'] : null;
$liveUrl = $status['liveUrl'] ?? null;
?>
<div id="editor-status-panel" class="cms-status-pipeline" <?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<h2 class="heading"><?= escape(__('status:heading')) ?></h2>
	<ol class="stages">
		<?php foreach ($stages as $stage): ?>
			<?php
			$key = (string) $stage['key'];
			$state = (string) $stage['state'];
			?>
			<li class="stage" data-stage="<?= escape($key) ?>" data-state="<?= escape($state) ?>">
				<span class="dot" data-stage="<?= escape($key) ?>" data-state="<?= escape($state) ?>"></span>
				<div class="body">
					<p class="name"><?= escape((string) $stage['name']) ?></p>
					<p class="help"><?= escape((string) $stage['help']) ?></p>
					<p class="text" data-stage-text><?= escape((string) $stage['text']) ?></p>
					<?php if ($key === 'main' && is_string($liveUrl) && $state === 'published'): ?>
						<div class="actions">
							<a class="cms-button secondary small" href="<?= escape($liveUrl) ?>" target="_blank" rel="noopener">
								<?= escape(__('status:view')) ?>
								<?= Icon::render('box-arrow-up-right') ?>
							</a>
						</div>
					<?php elseif ($key === 'draft' && ($status['discardDraft'] ?? false)): ?>
						<div class="actions">
							<button type="submit" class="cms-button secondary small" form="node-editor-discard">
								<?= escape(__('status:discard')) ?>
							</button>
						</div>
					<?php elseif ($key === 'mine' && ($status['autosave'] ?? false)): ?>
						<div class="actions" data-mine-actions <?= $state === 'none' ? 'hidden' : '' ?>>
							<button type="submit" class="cms-button secondary small" form="editor-autosave-discard">
								<?= escape(__('status:discard')) ?>
							</button>
						</div>
					<?php endif ?>
					<?php if ($key === 'main' && $visibility !== null): ?>
						<label class="toggle">
							<span class="copy">
								<span class="title"><?= escape(__('editor:published-label')) ?></span>
								<span class="help"><?= escape(__('editor:published-help')) ?></span>
							</span>
							<input
								id="editor-published-switch"
								type="checkbox"
								class="cms-switch"
								form="node-editor-visibility"
								name="published"
								value="1"
								data-editor-state
								<?= $visibility['published'] ? 'checked' : '' ?> />
						</label>
					<?php endif ?>
				</div>
			</li>
		<?php endforeach ?>
	</ol>
	<?php if ($visibility !== null): ?>
		<label class="toggle listed">
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
				<?= $visibility['hidden'] ? '' : 'checked' ?> />
		</label>
	<?php endif ?>
</div>
