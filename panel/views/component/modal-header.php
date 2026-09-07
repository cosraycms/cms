<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

?>
<header class="modal-header">
	<h2 class="modal-title" data-dialog-title><?= escape((string) $title) ?></h2>
	<button type="button" class="modal-close" data-dialog-close aria-label="<?= escape(__('field:close')) ?>">
		<?= Icon::render('x-lg') ?>
	</button>
</header>
