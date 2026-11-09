<?php

use function Cosray\escape;

// Out-of-band response for editor form submissions: the form itself is
// never re-rendered (the client state is the source of truth); only the
// status chip and the error box are swapped by id. The ids and payload
// keys are the internal save-response contract — see "Save response" in
// docs/controls.md before changing either side.

$saved = (bool) $saved;
$message = (string) $message;
$errors = (array) u($errors);
$published = (bool) (u($published ?? null) ?? false);
$renderable = (bool) (u($renderable ?? null) ?? false);
$preview = u($preview ?? null);
$draft = u($draft ?? null);
$draft = is_array($draft) ? $draft : null;

// The controller reduces validation issues to message + path; anything
// else is not renderable and must not be swallowed quietly by walking
// into it.
$issues = array_values(array_filter(
	$errors,
	static fn(mixed $issue): bool => is_array($issue) && is_string($issue['message'] ?? null),
));
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<?php // With issues to show, the summary box speaks for the failed save. ?>
<output
	id="editor-status"
	class="status <?= $saved ? 'is-success' : 'is-error' ?>"
	role="status"
	<?= $saved ? 'data-saved="true"' : '' ?>
	hx-swap-oob="true"><?= $saved || $issues === [] ? escape($message) : '' ?></output>
<?php // errors.js turns the issues into the jump chips, the field messages and

// the header's problem count, and keeps them current while the user edits. ?>
<div
	id="editor-errors"
	class="errors"
	tabindex="-1"
	hx-swap-oob="true"
	<?= $saved || $issues === [] ? 'hidden' : 'role="alert"' ?>>
	<?php if (!$saved && $issues !== []): ?>
		<span class="mark" aria-hidden="true">!</span>
		<div class="summary">
			<p class="title"><?= escape(__('editor:not-saved')) ?></p>
			<ul class="issues">
				<?php foreach ($issues as $issue): ?>
					<?php $path = $issue['path'] ?? null; ?>
					<li>
						<?php if (is_array($path) && $path !== []): ?>
							<?php // Single-quoted like data-when: JSON_HEX_APOS keeps the

							// JSON safe inside the attribute without double-escaping. ?>
							<button
								type="button"
								data-error-path='<?= json_encode($path, $jsonFlags) ?>'
								data-error-code="<?= escape((string) ($issue['code'] ?? '')) ?>">
								<?= escape($issue['message']) ?>
							</button>
						<?php else: ?>
							<?= escape($issue['message']) ?>
						<?php endif ?>
					</li>
				<?php endforeach ?>
			</ul>
		</div>
	<?php endif ?>
</div>
<?php if ($saved && $renderable): ?>
	<span
		id="editor-published"
		class="cms-status <?= $published ? 'is-published' : 'is-unpublished' ?>"
		hx-swap-oob="true"><?= escape($published ? __('editor:published') : __('editor:unpublished')) ?></span>
	<?php // Save and publish changes the flag behind the switch's back; left
	// stale, the switch would unpublish the node again on the next save. ?>
	<input
		id="editor-published-switch"
		type="checkbox"
		class="cms-switch"
		name="published"
		value="1"
		hx-swap-oob="true"
		<?= $published ? 'checked' : '' ?> />
	<?php // The working copy came or went with this save: the chip, the save

	// menu's entries and the note under the switch follow it. ?>
	<span
		id="editor-changes"
		class="cms-status is-changes"
		hx-swap-oob="true"
		<?= $draft ? '' : 'hidden' ?>><?= escape(__('editor:changes')) ?></span>
	<?php $this->include('node/save-options', ['draft' => $draft, 'oob' => true]) ?>
	<?php $this->include('node/changes-note', ['draft' => $draft, 'oob' => true]) ?>
<?php endif ?>
<?php if ($saved && is_array(u($autosave ?? null))): ?>
	<?php $this->include('node/autosave', ['autosave' => $autosave, 'oob' => true]) ?>
<?php endif ?>
<?php if ($saved && is_string($preview) && $preview !== ''): ?>
	<div id="editor-preview" class="preview" hx-swap-oob="true">
		<button type="button" class="close" data-overlay-close>
			<?= escape(__('editor:close')) ?>
		</button>
		<iframe src="<?= escape($preview) ?>" title="<?= escape(__('editor:preview')) ?>"></iframe>
	</div>
<?php endif ?>
