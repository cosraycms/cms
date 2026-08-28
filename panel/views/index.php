<?php

use function Cosray\escape;

$this->layout('layer/main');

$cards = (array) $this->unwrap($cards ?? []);
$recent = (array) $this->unwrap($recent ?? []);
?>

<div class="page cms-dashboard">
	<header class="head">
		<h1><?= escape(__('nav:dashboard')) ?></h1>
	</header>

	<section class="body">
		<?php if ($cards !== []): ?>
			<div class="cards">
				<?php foreach ($cards as $card): ?>
					<?php

					$card = (array) $this->unwrap($card);
					$url = $card['url'] ?? null;
					$linked = is_string($url) && trim($url) !== '';
					?>
					<?php if ($linked): ?>
						<a class="card" href="<?= escape($url) ?>" hx-target="#frame">
					<?php else: ?>
						<article class="card">
					<?php endif ?>
						<span class="label"><?= escape((string) ($card['label'] ?? '')) ?></span>
						<strong class="value"><?= escape((string) ($card['value'] ?? '')) ?></strong>
						<?php if (trim((string) ($card['note'] ?? '')) !== ''): ?>
							<span class="note"><?= escape((string) $card['note']) ?></span>
						<?php endif ?>
					<?php if ($linked): ?>
						</a>
					<?php else: ?>
						</article>
					<?php endif ?>
				<?php endforeach ?>
			</div>
		<?php endif ?>

		<section class="recent" aria-labelledby="dashboard-recent-title">
			<h2 id="dashboard-recent-title"><?= escape(__('dashboard:recent')) ?></h2>

			<?php if ($recent === []): ?>
				<p class="empty"><?= escape(__('dashboard:recent-empty')) ?></p>
			<?php else: ?>
				<div class="rows">
					<?php foreach ($recent as $row): ?>
						<?php $row = (array) $this->unwrap($row) ?>
						<div class="row">
							<span
								class="dot <?= $row['published'] ?? false ? 'is-published' : 'is-draft' ?>"
								aria-hidden="true"></span>
							<span class="status sr-only"><?= escape((string) ($row['status'] ?? '')) ?></span>
							<span class="title"><?= escape((string) ($row['title'] ?? '')) ?></span>
							<span class="type"><?= escape((string) ($row['type'] ?? '')) ?></span>
							<time class="changed" datetime="<?= escape((string) ($row['datetime'] ?? '')) ?>"><?= escape(
								(string) ($row['changed'] ?? ''),
							) ?></time>
						</div>
					<?php endforeach ?>
				</div>
			<?php endif ?>
		</section>
	</section>
</div>
