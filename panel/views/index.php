<?php

use function Cosray\escape;

$this->layout('layer/main');
?>

<div class="page cms-dashboard">
	<header class="head">
		<h1><?= escape(__('nav:dashboard')) ?></h1>
	</header>

	<section class="body"></section>
</div>
