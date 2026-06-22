<?php $this->layout('base') ?>

<div class="app">
	<?php $this->insert('component/navigation') ?>

	<main class="main">
		<?= $this->body() ?>
	</main>
</div>
