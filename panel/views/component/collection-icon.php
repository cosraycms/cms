<?php

$iconMeta = $this->unwrap($iconMeta ?? null);
$default = (bool) ($default ?? false);
$icon = is_array($iconMeta) ? (string) $this->unwrap($renderIcon($iconMeta)) : '';
?>
<?php if ($iconMeta === null && $default): ?>
	<span class="icon" aria-hidden="true"><?php $this->insert('icon/collection.svg') ?></span>
<?php elseif ($icon !== ''): ?>
	<span class="icon" aria-hidden="true"><?= $icon ?></span>
<?php endif ?>
