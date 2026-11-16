<?php

$this->layout('layer/main');
?>

<div class="cms-page test-plugin-page">
	<header class="cms-page-head">
		<h1><?= $title ?></h1>
<?php if (isset($statsUrl)): ?>
		<a class="stats" href="<?= $statsUrl ?>">Stats</a>
<?php endif ?>
	</header>
</div>
