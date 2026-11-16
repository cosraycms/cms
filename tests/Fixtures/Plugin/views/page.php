<?php

$this->layout('layer/main');
?>

<div class="cms-page test-plugin-page">
	<header class="cms-page-head">
		<h1><?= $title ?></h1>
		<p class="greeting"><?= __('test-plugin:greeting') ?></p>
		<p class="english"><?= __('test-plugin:only-english') ?></p>
<?php if (isset($statsUrl)): ?>
		<a class="stats" href="<?= $statsUrl ?>">Stats</a>
<?php endif ?>
	</header>
</div>
