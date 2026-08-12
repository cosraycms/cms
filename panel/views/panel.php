<?php $this->layout('base') ?>

<div class="cms-shell">
	<?php $this->insert('component/masthead') ?>

	<div class="frame">
		<?php $this->insert('component/navigation') ?>

		<main class="main">
			<?= $this->body() ?>
		</main>
	</div>
</div>
