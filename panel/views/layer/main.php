<?php

// The content region alone: what navigating inside an area swaps. Every panel
// page lays out against this and lets the layer decide how much wraps it.

$layer = (string) $layer;

if ($layer !== 'main') {
	$this->layout('layer/frame');
}

?>
<?= $this->body() ?>
<?php if ($layer === 'main'): ?>
	<?php $this->insert('component/rail-nav', ['oob' => true]) ?>
<?php endif ?>
