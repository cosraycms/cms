<?php

use function Cosray\escape;

// Out-of-band response of the editor's preview: the dialog with the working
// state and the user's edits on top, which the request kept without saving
// them, and the status with those edits.

$preview = (string) u($preview);
?>
<dialog id="editor-preview" class="cms-modal cms-node-preview" data-size="full" hx-swap-oob="true">
	<?php $this->include('component/modal-header', ['title' => __('editor:preview')]) ?>
	<iframe src="<?= escape($preview) ?>" title="<?= escape(__('editor:preview')) ?>"></iframe>
</dialog>
<?php $this->include('component/status-dots', [
	'status' => $status,
	'id' => 'editor-status-dots',
	'oob' => true,
]) ?>
<?php $this->include('node/status', ['status' => $status, 'oob' => true]) ?>
<?php $this->include('node/discard', ['status' => $status, 'oob' => true]) ?>
<?php if ($status['drafts'] ?? true): ?>
	<?php $this->include('node/apply', ['status' => $status, 'oob' => true]) ?>
<?php endif ?>
