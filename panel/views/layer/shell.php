<?php

// The masthead and the frame below it. Marked as the history element, so a
// back or forward restore swaps this and leaves the scripts around it alone;
// that is where such a request stops.

$layer = (string) $layer;

if ($layer !== 'shell') {
	$this->layout('layer/document');
}

?>
<div class="cms-shell" hx-history-elt>
	<?php $this->insert('component/masthead') ?>

	<?= $this->body() ?>
</div>
