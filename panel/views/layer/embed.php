<?php

// An editor framed by another application: the document without masthead
// and navigation, so only the form shows. Every request in it renders whole.

$this->layout('layer/document');

?>
<main id="main" class="main cms-embed" hx-target:inherited="#main">
	<?= $this->slot() ?>
</main>
