<?php

// The rail and the content region. The masthead switches areas by swapping
// this, which is why the areas it does not re-render patch themselves in.

$layer = (string) $layer;

if ($layer !== 'frame') {
	$this->layout('layer/shell');
}

?>
<div id="frame" class="frame">
	<?php $this->insert('component/navigation') ?>

	<main id="main" class="main" hx-target:inherited="#main">
		<?= $this->body() ?>
	</main>
</div>
<?php if ($layer === 'frame'): ?>
	<?php $this->insert('component/area-nav', ['oob' => true]) ?>
<?php endif ?>
