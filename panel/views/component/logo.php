<a
	class="logo"
	href="<?= $panelPath ?>"
	hx-target="#main"
	aria-label="Dashboard">
	<?php if ($logo !== null): ?>
		<img class="image" src="<?= $logo ?>" alt="Panel Logo" />
	<?php else: ?>
		<span class="mark" aria-hidden="true">D</span>
		<span class="wordmark">Cosray</span>
	<?php endif ?>
</a>
