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
// The node editor's status view; without it (users, embedded editors) the
// head's status output says how the save went.
$status = u($status ?? null);
$status = is_array($status) ? $status : null;
$toast = $status !== null || (bool) (u($toast ?? null) ?? false);

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
	<?= $toast ? 'hidden' : '' ?>
	hx-swap-oob="true"><?= !$toast && ($saved || $issues === []) ? escape($message) : '' ?></output>
<?php if ($toast && ($saved || $issues === []) && $message !== ''): ?>
	<?php $this->include('component/toast', ['kind' => $saved ? 'success' : 'error', 'message' => $message]) ?>
<?php endif ?>
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
<?php if ($status !== null): ?>
	<?php // The stages follow the save, or a refusal: the head's dots, the status tab,

	// and the banner of edits on an outdated state. ?>
	<?php $this->include('component/status-dots', [
		'status' => $status,
		'id' => 'editor-status-dots',
		'oob' => true,
	]) ?>
	<?php $this->include('node/status', ['status' => $status, 'oob' => true]) ?>
	<?php $this->include('node/conflict', ['status' => $status, 'oob' => true]) ?>
<?php endif ?>
<?php if ($saved && is_array(u($autosave ?? null))): ?>
	<?php $this->include('node/autosave', ['autosave' => $autosave, 'oob' => true]) ?>
<?php endif ?>

