<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The layout preview dialog, one per editor page: a blocks field rendered
// through the site's render path from the form as it stands, in a frame
// that runs no script. The layout-preview behavior posts the form to the
// URL plus `/{field}`, fills the frame, and sizes it to the chosen width;
// the presets are the common device widths, the last choice is remembered
// per browser. Receives: url.

$url = (string) $this->unwrap($url);
$presets = [
	['id' => 'desktop', 'width' => 1200, 'icon' => 'display', 'label' => __('editor:layout-preview-desktop')],
	['id' => 'laptop', 'width' => 1024, 'icon' => 'laptop', 'label' => __('editor:layout-preview-laptop')],
	['id' => 'tablet', 'width' => 768, 'icon' => 'tablet', 'label' => __('editor:layout-preview-tablet')],
	['id' => 'phone', 'width' => 390, 'icon' => 'phone', 'label' => __('editor:layout-preview-phone')],
];
?>
<dialog
	class="cms-modal cms-layout-preview"
	data-size="wide"
	data-layout-preview-dialog
	data-url="<?= escape($url) ?>"
	data-error="<?= escape(__('editor:layout-preview-failed')) ?>">
	<header class="modal-header">
		<h2 class="modal-title" data-dialog-title><?= escape(__('editor:layout-preview')) ?></h2>
		<span class="note"><?= escape(__('editor:layout-preview-note')) ?></span>
		<div class="widths" role="group" aria-label="<?= escape(__('editor:layout-preview-width')) ?>">
			<?php foreach ($presets as $preset): ?>
				<button
					type="button"
					class="option"
					aria-pressed="false"
					title="<?= escape("{$preset['label']} ({$preset['width']} px)") ?>"
					data-layout-preview-width="<?= $preset['width'] ?>">
					<?= Icon::render($preset['icon']) ?>
					<span class="text"><?= escape($preset['label']) ?></span>
				</button>
			<?php endforeach ?>
		</div>
		<button
			type="button"
			class="cms-button secondary small reload"
			data-layout-preview-reload
			aria-label="<?= escape(__('editor:layout-preview-reload')) ?>"
			title="<?= escape(__('editor:layout-preview-reload')) ?>">
			<?= Icon::render('arrow-clockwise') ?>
		</button>
		<button type="button" class="modal-close" data-dialog-close aria-label="<?= escape(__('field:close')) ?>">
			<?= Icon::render('x-lg') ?>
		</button>
	</header>
	<div class="modal-body">
		<div class="stage" data-layout-preview-stage>
			<?php // No scripts: the iframe block emits editor markup raw. Same origin

			// stays on so permission-checked renditions receive the session. ?>
			<iframe
				class="frame"
				title="<?= escape(__('editor:layout-preview')) ?>"
				sandbox="allow-same-origin"
				data-layout-preview-frame></iframe>
		</div>
	</div>
</dialog>
