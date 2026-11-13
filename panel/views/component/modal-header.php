<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

?>
<header class="cms-modal-header">
	<h2 class="cms-modal-title" data-dialog-title><?= escape((string) $title) ?></h2>
	<button type="button" class="cms-modal-close" data-dialog-close aria-label="<?= escape(__('common:close')) ?>">
		<?= Icon::render('x-lg') ?>
	</button>
</header>
