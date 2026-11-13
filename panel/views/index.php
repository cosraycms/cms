<?php

use function Cosray\escape;

$this->layout('layer/main');

$cards = (array) u($cards ?? []);
$recent = (array) u($recent ?? []);
?>

<div class="cms-page cms-dashboard">
	<header class="cms-page-head">
		<h1><?= escape(__('nav:dashboard')) ?></h1>
	</header>

	<section class="cms-dashboard-body">
		<?php if ($cards !== []): ?>
			<div class="cms-dashboard-cards">
				<?php foreach ($cards as $card): ?>
					<?php

					$card = (array) u($card);
					$url = $card['url'] ?? null;
					$linked = is_string($url) && trim($url) !== '';
					?>
					<?php if ($linked): ?>
						<a class="cms-dashboard-card" href="<?= escape($url) ?>" hx-target="#frame">
					<?php else: ?>
						<article class="cms-dashboard-card">
					<?php endif ?>
						<span class="cms-dashboard-label"><?= escape((string) ($card['label'] ?? '')) ?></span>
						<strong class="cms-dashboard-value"><?= escape((string) ($card['value'] ?? '')) ?></strong>
						<?php if (trim((string) ($card['note'] ?? '')) !== ''): ?>
							<span class="cms-dashboard-note"><?= escape((string) $card['note']) ?></span>
						<?php endif ?>
					<?php if ($linked): ?>
						</a>
					<?php else: ?>
						</article>
					<?php endif ?>
				<?php endforeach ?>
			</div>
		<?php endif ?>

		<section class="cms-dashboard-recent" aria-labelledby="dashboard-recent-title">
			<h2 id="dashboard-recent-title"><?= escape(__('dashboard:recent')) ?></h2>

			<?php if ($recent === []): ?>
				<p class="cms-dashboard-empty"><?= escape(__('dashboard:recent-empty')) ?></p>
			<?php else: ?>
				<div>
					<?php foreach ($recent as $row): ?>
						<?php $row = (array) u($row) ?>
						<div class="cms-dashboard-row">
							<?php if (is_array($row['dots'] ?? null)): ?>
								<?php $this->include('component/status-dots', ['status' => $row['dots'], 'size' => 'sm']) ?>
							<?php endif ?>
							<a class="cms-dashboard-title" href="<?= escape((string) $row['url']) ?>" hx-target="#frame"><?= escape(
								(string) ($row['title'] ?? ''),
							) ?></a>
							<span class="cms-dashboard-type"><?= escape((string) ($row['type'] ?? '')) ?></span>
							<time class="cms-dashboard-changed" datetime="<?= escape((string) ($row['datetime'] ?? '')) ?>"><?= escape(
								(string) ($row['changed'] ?? ''),
							) ?></time>
						</div>
					<?php endforeach ?>
				</div>
			<?php endif ?>
		</section>
	</section>
</div>
