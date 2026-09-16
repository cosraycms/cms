<?php

// Area switches replace the frame's innerHTML, so only a parent layer
// renders the wrapper. The masthead stays in place and patches itself in.

$layer = (string) $layer;

if ($layer !== 'frame') {
	$this->layout('layer/shell');
}

?>
<?php if ($layer !== 'frame'): ?>
<div id="frame" class="frame">
<?php endif ?>
	<?php $this->insert('component/navigation') ?>

	<main id="main" class="main" hx-target:inherited="#main">
		<?= $this->body() ?>
	</main>
<?php if ($layer !== 'frame'): ?>
</div>
<?php endif ?>
<?php if ($layer === 'frame'): ?>
	<?php $this->insert('component/area-nav', ['oob' => true]) ?>
<?php endif ?>
