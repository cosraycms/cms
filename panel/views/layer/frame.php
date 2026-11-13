<?php

// Area switches replace the frame's innerHTML, so only a parent layer
// renders the wrapper. The masthead stays in place and patches itself in.

$layer = (string) $layer;

if ($layer !== 'frame') {
	$this->layout('layer/shell');
}

?>
<?php if ($layer !== 'frame'): ?>
<div id="frame" class="cms-shell-frame">
<?php endif ?>
	<?php $this->include('component/navigation') ?>

	<main id="main" class="cms-shell-main" hx-target:inherited="#main">
		<?= $this->slot() ?>
	</main>
<?php if ($layer !== 'frame'): ?>
</div>
<?php endif ?>
<?php if ($layer === 'frame'): ?>
	<?php $this->include('component/area-nav', ['oob' => true]) ?>
<?php endif ?>
