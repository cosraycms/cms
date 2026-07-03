<?php

use function Cosray\escape;

// Out-of-band response for editor form submissions: the form itself is
// never re-rendered (the client state is the source of truth); only the
// status chip and the error box are swapped by id.

$saved = (bool) $saved;
$message = (string) $message;
$errors = (array) $this->unwrap($errors);

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
