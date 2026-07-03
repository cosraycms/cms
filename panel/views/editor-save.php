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

$flatten = static function (mixed $issues, callable $self): array {
	if (is_string($issues)) {
		return [$issues];
	}

	if (!is_array($issues)) {
		return [];
	}

	$messages = [];

	foreach ($issues as $issue) {
		$messages = [...$messages, ...$self($issue, $self)];
	}

	return $messages;
};
$messages = $flatten($errors, $flatten);
?>
<output
	id="editor-status"
	class="editor-status <?= $saved ? 'is-success' : 'is-error' ?>"
	role="status"
	<?= $saved ? 'data-saved="true"' : '' ?>
	hx-swap-oob="true"><?= escape($message) ?></output>
<div
	id="editor-errors"
	class="editor-errors"
	hx-swap-oob="true"
	<?= $saved || $messages === [] ? 'hidden' : '' ?>>
	<?php if (!$saved && $messages !== []): ?>
		<ul>
			<?php foreach ($messages as $error): ?>
				<li><?= escape($error) ?></li>
			<?php endforeach ?>
		</ul>
	<?php endif ?>
</div>
<?php if ($saved && $renderable): ?>
	<span
		id="editor-published"
		class="cms-published large<?= $published ? ' published' : '' ?>"
		hx-swap-oob="true"><?= escape($published ? _('veröffentlicht') : _('unveröffentlicht')) ?></span>
<?php endif ?>
<?php if ($saved && is_string($preview) && $preview !== ''): ?>
	<div id="editor-preview" class="editor-preview" hx-swap-oob="true">
		<button type="button" class="cms-preview-close" data-overlay-close>
			<?= escape(_('schließen')) ?>
		</button>
		<iframe src="/preview<?= escape($preview) ?>" title="Preview"></iframe>
	</div>
<?php endif ?>
