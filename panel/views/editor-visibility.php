<?php

// Out-of-band response of the visibility switches: the status in the head
// and the inspector, with the switches, and a toast saying what changed.

$message = u($message ?? null);
?>
<?php $this->include('component/status-dots', [
	'status' => $status,
	'id' => 'editor-status-dots',
	'oob' => true,
]) ?>
<?php $this->include('node/status', ['status' => $status, 'oob' => true]) ?>
<?php $this->include('node/discard', ['status' => $status, 'oob' => true]) ?>
<?php if (!($status['record'] ?? false)): ?>
	<?php $this->include('node/apply', ['status' => $status, 'oob' => true]) ?>
<?php endif ?>
<?php if (is_string($message) && $message !== ''): ?>
	<?php $this->include('component/toast', ['kind' => $kind, 'message' => $message]) ?>
<?php endif ?>
