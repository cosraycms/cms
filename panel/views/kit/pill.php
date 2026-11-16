<?php

use function Cosray\escape;

// A short label in a tone: neutral, info, success, warning or danger.
// Receives: label, tone (neutral by default).

$tone = (string) u($tone ?? 'neutral');
?>
<span class="cms-kit-pill" data-tone="<?= escape($tone) ?>"><?= escape((string) u($label)) ?></span>
