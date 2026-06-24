<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('panel');
}
?>

<div id="main" class="page dashboard-page">
	<header class="topbar">
		<h1><?= escape((string) $config->app->name) ?></h1>
	</header>

	<section class="content">
		<div class="page-head">
			<h1>Dashboard</h1>
		</div>
	</section>
</div>
