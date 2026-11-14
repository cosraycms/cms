<?php

use Cosray\Panel\Icon;
use Cosray\Panel\NodeStatus;

use function Cosray\escape;

// The inspector's status tab: the node's stages top to bottom, from
// Panel\NodeStatus, each with what it means and what can be done there, and
// between two stages the step that takes the lower one into the upper one.
// The publication and listing switches below belong to the editor's
// visibility form, outside the editor form, and act at once.
// data-editor-state keeps them from marking the editor as edited. Rendered
// with the editor and again, out of band, after every save and switch.
// status.js moves the mine stage and the steps between saves.
// Receives: status (the editor's status view), oob.

$status = (array) u($status);
$oob = (bool) (u($oob ?? null) ?? false);
$stages = (array) ($status['stages'] ?? []);
$record = (bool) ($status['record'] ?? false);
$visibility = is_array($status['visibility'] ?? null) ? $status['visibility'] : null;
$conflict = is_array($status['conflict'] ?? null) ? $status['conflict'] : null;
$mine = (string) ($status['mine'] ?? 'none');
$draft = (bool) ($status['draft'] ?? false);
$saveLabel = $record ? 'editor:save' : 'editor:save-draft';
?>
<div id="editor-status-panel" class="cms-status-pipeline" <?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<h2 class="cms-status-pipeline-heading"><?= escape(__('status:heading')) ?></h2>
	<ol class="cms-status-pipeline-stages">
		<?php foreach ($stages as $stage): ?>
			<?php
			$key = (string) $stage['key'];
			$state = (string) $stage['state'];
			$badge = (string) ($stage['badge'] ?? '');
			?>
			<li class="cms-status-pipeline-stage" data-stage="<?= escape($key) ?>" data-state="<?= escape($state) ?>">
				<span class="cms-stage-mark" data-stage="<?= escape($key) ?>" data-state="<?= escape($state) ?>">
					<?= Icon::render(NodeStatus::icon($key, $state)) ?>
				</span>
				<div class="cms-status-pipeline-body">
					<p class="cms-status-pipeline-title">
						<span class="cms-status-pipeline-name"><?= escape((string) $stage['name']) ?></span>
						<?php if ($badge !== ''): ?>
							<span class="cms-status-pipeline-badge is-<?= escape($state) ?>"><?= escape($badge) ?></span>
						<?php endif ?>
						<?php if ($key === 'draft' && ($status['discardDraft'] ?? false)): ?>
							<button
								type="button"
								class="cms-status-pipeline-discard"
								popovertarget="editor-discard-draft"
								aria-label="<?= escape(__('status:discard-draft')) ?>">
								<?= escape(__('status:discard')) ?>
							</button>
						<?php elseif ($key === 'mine' && ($status['autosave'] ?? false)): ?>
							<button
								type="button"
								class="cms-status-pipeline-discard"
								popovertarget="editor-discard-mine"
								aria-label="<?= escape(__('status:discard-mine')) ?>"
								data-mine-discard
								<?= $state === 'none' ? 'hidden' : '' ?>>
								<?= escape(__('status:discard')) ?>
							</button>
						<?php endif ?>
					</p>
					<p class="cms-status-pipeline-help"><?= escape((string) $stage['help']) ?></p>
					<p class="cms-status-pipeline-text" data-stage-text><?= escape((string) $stage['text']) ?></p>
					<?php if ($key === 'mine' && $conflict !== null): ?>
						<div class="cms-status-pipeline-notice">
							<?= Icon::render('exclamation-triangle') ?>
							<p><?= escape(__('editor:conflict-help', [
								'editor' => $conflict['editor'] !== '' ? $conflict['editor'] : __('editor:conflict-someone'),
								'time' => $conflict['time'],
							])) ?></p>
						</div>
					<?php endif ?>
				</div>
				<?php if ($key === 'main'): ?>
					<div class="cms-status-pipeline-step" data-step="apply" <?= $draft || $mine !== 'none' ? '' : 'hidden' ?>>
						<span class="cms-status-pipeline-arrow"><?= Icon::render('arrow-up') ?></span>
						<div class="cms-status-pipeline-body">
							<button
								type="button"
								class="cms-button cms-button-secondary cms-button-small"
								popovertarget="editor-apply-confirm"
								<?= $mine === 'conflict' ? 'disabled' : '' ?>>
								<?= Icon::render('arrow-bar-up') ?>
								<?= escape(__('status:apply-step')) ?>
							</button>
							<p
								class="cms-status-pipeline-help"
								data-step-help
								data-both="<?= escape(__('status:apply-both')) ?>"
								data-draft="<?= escape(__('status:apply-draft')) ?>"
								data-mine="<?= escape(__('status:apply-mine')) ?>"><?= escape(__(match (true) {
									$draft && $mine !== 'none' => 'status:apply-both',
									$draft => 'status:apply-draft',
									default => 'status:apply-mine',
								})) ?></p>
						</div>
					</div>
				<?php elseif ($key === 'draft' || $key === 'saved'): ?>
					<div class="cms-status-pipeline-step" data-step="save" <?= $mine === 'none' ? 'hidden' : '' ?>>
						<span class="cms-status-pipeline-arrow"><?= Icon::render('arrow-up') ?></span>
						<div class="cms-status-pipeline-body">
							<button
								type="submit"
								class="cms-button cms-button-secondary cms-button-small"
								form="node-editor-form"
								data-editor-submit
								data-needs="mine">
								<?= Icon::render('floppy') ?>
								<span
									data-save-label
									data-normal="<?= escape(__($saveLabel)) ?>"
									data-conflict="<?= escape(__('editor:save-anyway')) ?>"><?= escape(__(
									$mine === 'conflict' ? 'editor:save-anyway' : $saveLabel,
								)) ?></span>
							</button>
							<p class="cms-status-pipeline-help"><?= escape(__($record ? 'status:save-record-help' : 'status:save-help')) ?></p>
						</div>
					</div>
				<?php endif ?>
			</li>
		<?php endforeach ?>
	</ol>
	<?php if ($visibility !== null): ?>
		<div class="cms-status-pipeline-visibility">
			<div class="cms-status-pipeline-bar">
				<h3 class="cms-status-pipeline-heading"><?= escape(__('status:visibility')) ?></h3>
				<span class="cms-status-pipeline-instant"><?= Icon::render('lightning') ?><?= escape(__('status:instant')) ?></span>
			</div>
			<label class="cms-inspector-toggle">
				<span class="cms-inspector-copy">
					<span class="cms-inspector-title"><?= escape(__('editor:published-label')) ?></span>
					<span class="cms-inspector-help"><?= escape(__('editor:published-help')) ?></span>
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
			<label class="cms-inspector-toggle">
				<span class="cms-inspector-copy">
					<span class="cms-inspector-title"><?= escape(__('editor:listed-label')) ?></span>
					<span class="cms-inspector-help"><?= escape(__('editor:listed-help')) ?></span>
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
		</div>
	<?php endif ?>
</div>
