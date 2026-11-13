<?php

$iconMeta = u($iconMeta ?? null);
$default = (bool) ($default ?? false);
$icon = is_array($iconMeta) ? (string) u($renderIcon($iconMeta)) : '';
?>
<?php if ($iconMeta === null && $default): ?>
	<span class="cms-collection-icon" aria-hidden="true"><?= \Cosray\Panel\Icon::render('collection') ?></span>
<?php elseif ($icon !== ''): ?>
	<span class="cms-collection-icon" aria-hidden="true"><?= $icon ?></span>
<?php endif ?>
