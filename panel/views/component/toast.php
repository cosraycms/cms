<?php

use function Cosray\escape;

// A toast a response adds to the document's region out of band; lib/toast.js
// turns it into a closable one, and on a full page load moves it there first.
// Receives: kind (success, info, warning or error), message.

$kind = (string) u($kind ?? 'info');
$message = (string) u($message);
?>
<div hx-swap-oob="beforeend:#cms-toasts" hidden>
	<div data-toast="<?= escape($kind) ?>"><?= escape($message) ?></div>
</div>
