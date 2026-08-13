<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('panel');
}
?>

<div id="main" class="page cms-dashboard">
	<header class="head">
		<h1><?= escape(__('nav:dashboard')) ?></h1>
	</header>

	<section class="body"></section>
</div>
