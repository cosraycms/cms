<?php

use function Cosray\escape;

$name = u($name ?? null);
$size = u($size ?? null);

?>
<dialog class="cms-modal"<?php if (is_string($name)): ?> data-dialog="<?= escape($name) ?>"<?php endif ?><?php if (
	is_string($size)
): ?> data-size="<?= escape($size) ?>"<?php endif ?>>
	<?php $this->include('component/modal-header', ['title' => $title]) ?>
	<?= $this->slot() ?>
</dialog>
