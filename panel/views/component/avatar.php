<?php

use function Cosray\escape;

// A user's initials on a colour their uid fixes, from User\Avatar::array().
// Beside the user's name it stays hidden from assistive technology; standing
// alone (named) it carries the name as its accessible name and tooltip.
// Receives: avatar, size (md, sm), named.

$avatar = (array) u($avatar);
$size = (string) u($size ?? 'md');
$named = (bool) (u($named ?? null) ?? false);
$label = (string) $avatar['label'];
?>
<span
	class="cms-avatar is-<?= escape($size) ?>"
	style="--hue: <?= (int) $avatar['hue'] ?>"
	<?php if ($named): ?>
		role="img"
		aria-label="<?= escape($label) ?>"
		title="<?= escape($label) ?>"
	<?php else: ?>
		aria-hidden="true"
	<?php endif ?>><?= escape((string) $avatar['initials']) ?></span>
