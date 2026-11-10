<?php

// Out-of-band response of the visibility switches: the switches with the
// node's state and a toast saying what changed.

$message = u($message ?? null);
?>
<?php $this->include('node/visibility', ['visibility' => $visibility, 'oob' => true]) ?>
<?php if (is_string($message) && $message !== ''): ?>
	<?php $this->include('component/toast', ['kind' => $kind, 'message' => $message]) ?>
<?php endif ?>
