<?php

use function Cosray\escape;

// Out-of-band response of the visibility switches: the status in the head
// and the inspector, with the switches, a toast saying what changed, and the
// node's change time before and after, for status.js to move the editor's
// base along when it was current.

$message = u($message ?? null);
$rebase = (array) u($rebase);
?>
<span
	id="editor-rebase"
	hidden
	data-from="<?= escape((string) ($rebase['from'] ?? '')) ?>"
	data-to="<?= escape((string) ($rebase['to'] ?? '')) ?>"
	hx-swap-oob="true"></span>
<?php $this->include('component/status-dots', [
	'status' => $status,
	'id' => 'editor-status-dots',
	'oob' => true,
]) ?>
<?php $this->include('node/status', ['status' => $status, 'oob' => true]) ?>
<?php if (!($status['record'] ?? false)): ?>
	<?php $this->include('node/apply', ['status' => $status, 'oob' => true]) ?>
<?php endif ?>
<?php if (is_string($message) && $message !== ''): ?>
	<?php $this->include('component/toast', ['kind' => $kind, 'message' => $message]) ?>
<?php endif ?>
