<?php declare(strict_types=1); ?>
<!doctype html>
<title><?= $node->title ?></title>
<main>
	<?php if ($node->cover): ?>
		<?= $node->cover->size('wide')->figure() ?>
	<?php endif ?>
	<?= $node->gallery?->gallery('thumb') ?>
</main>
