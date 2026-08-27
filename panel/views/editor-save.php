<?php

use function Cosray\escape;

// Out-of-band response for editor form submissions: the form itself is
// never re-rendered (the client state is the source of truth); only the
// status chip and the error box are swapped by id.

$saved = (bool) $saved;
$message = (string) $message;
$errors = (array) $this->unwrap($errors);
$published = (bool) ($this->unwrap($published ?? null) ?? false);
$renderable = (bool) ($this->unwrap($renderable ?? null) ?? false);
$preview = $this->unwrap($preview ?? null);

// The controller reduces validation issues to message + path; anything
// else is not renderable and must not be swallowed quietly by walking
// into it.
$issues = array_values(array_filter(
	$errors,
	static fn(mixed $issue): bool => is_array($issue) && is_string($issue['message'] ?? null),
));
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<output
	id="editor-status"
	class="status <?= $saved ? 'is-success' : 'is-error' ?>"
	role="status"
	<?= $saved ? 'data-saved="true"' : '' ?>
	hx-swap-oob="true"><?= escape($message) ?></output>
<div
	id="editor-errors"
	class="errors"
	tabindex="-1"
	hx-swap-oob="true"
	<?= $saved || $issues === [] ? 'hidden' : '' ?>>
	<?php if (!$saved && $issues !== []): ?>
		<ul>
			<?php foreach ($issues as $issue): ?>
				<?php $path = $issue['path'] ?? null; ?>
				<li>
					<?php if (is_array($path) && $path !== []): ?>
						<?php // Single-quoted like data-when: JSON_HEX_APOS keeps the

						// JSON safe inside the attribute without double-escaping. ?>
						<button type="button" data-error-path='<?= json_encode($path, $jsonFlags) ?>'>
							<?= escape($issue['message']) ?>
						</button>
					<?php else: ?>
						<?= escape($issue['message']) ?>
					<?php endif ?>
				</li>
			<?php endforeach ?>
		</ul>
	<?php endif ?>
</div>
<?php if ($saved && $renderable): ?>
	<span
		id="editor-published"
		class="cms-status <?= $published ? 'is-published' : 'is-unpublished' ?>"
		hx-swap-oob="true"><?= escape($published ? __('editor:published') : __('editor:unpublished')) ?></span>
<?php endif ?>
<?php if ($saved && is_string($preview) && $preview !== ''): ?>
	<div id="editor-preview" class="preview" hx-swap-oob="true">
		<button type="button" class="close" data-overlay-close>
			<?= escape(__('editor:close')) ?>
		</button>
		<iframe src="/preview<?= escape($preview) ?>" title="<?= escape(__('editor:preview')) ?>"></iframe>
	</div>
<?php endif ?>
