<?php if (!$boosted)
	$this->layout('app'); ?>

<section class="node-page">
	<header>
		<h1 class="node-title"><?= $title !== '' ? $title : $uid ?></h1>
	</header>

	<?php if ($saved): ?>
		<p class="node-saved" role="status">Saved.</p>
	<?php endif ?>

	<form class="node-form" method="post" action="<?= $panelPath ?>/node/<?= $uid ?>" hx-boost="true" hx-target="#main">
		<input type="hidden" name="uid" value="<?= $uid ?>">
		<input type="hidden" name="published" value="<?= $published ? '1' : '0' ?>">
		<input type="hidden" name="hidden" value="<?= $hidden ? '1' : '0' ?>">
		<input type="hidden" name="locked" value="<?= $locked ? '1' : '0' ?>">

		<div class="node-fields">
			<?php foreach ($fields as $field): ?>
				<?= $this->unwrap($field) ?>
			<?php endforeach ?>
		</div>

		<div class="node-actions">
			<button type="submit">Save</button>
		</div>
	</form>
</section>
