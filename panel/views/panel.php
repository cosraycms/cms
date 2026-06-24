<?php $this->layout('base') ?>

<div class="panel">
	<?php $this->insert('component/navigation') ?>

	<main class="main">
		<?= $this->body() ?>
	</main>
</div>
