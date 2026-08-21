<?php

// Everything inside <body>: the masthead and the frame below it. htmx restores
// history by swapping the body, so this is where such a request stops.

$layer = (string) $layer;

if ($layer !== 'shell') {
	$this->layout('layer/document');
}

?>
<div class="cms-shell">
	<?php $this->insert('component/masthead') ?>

	<?= $this->body() ?>
</div>
<?php if ($layer === 'shell'): ?>
	<?php $this->insert('component/catalog') ?>
<?php endif ?>
