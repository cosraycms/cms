<?php

use function Cosray\escape;

// Every form that posts carries it, for submits without the panel scripts.

?>
<input type="hidden" name="_token" value="<?= escape((string) ($csrfToken ?? '')) ?>" />
