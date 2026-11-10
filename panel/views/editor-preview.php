<?php

use function Cosray\escape;

// Out-of-band response of the editor's preview: the overlay with the working
// state and the user's edits on top, which the request kept without saving
// them, and the status with those edits.

$preview = (string) u($preview);
?>
<div id="editor-preview" class="preview" hx-swap-oob="true">
	<button type="button" class="close" data-overlay-close>
		<?= escape(__('editor:close')) ?>
	</button>
	<iframe src="<?= escape($preview) ?>" title="<?= escape(__('editor:preview')) ?>"></iframe>
</div>
<?php $this->include('component/status-dots', [
	'status' => $status,
	'id' => 'editor-status-dots',
	'oob' => true,
]) ?>
<?php $this->include('node/status', ['status' => $status, 'oob' => true]) ?>
