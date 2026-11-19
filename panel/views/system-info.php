<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

$this->layout('layer/main');

$tab = (string) u($tab);
$tabs = (array) u($tabs);
$url = (string) u($url);
$tones = ['fail' => 'danger', 'warn' => 'warning'];
?>

<div class="cms-page cms-kit-page cms-system-info">
	<?php $this->include('kit/head', ['title' => __('system-info:title')]) ?>

	<div class="cms-kit-body">
		<div class="cms-page-toolbar">
			<?php $this->include('kit/filter', ['label' => __('system-info:title'), 'links' => $tabs]) ?>
			<?php if ($tab === 'php'): ?>
				<form class="cms-page-search" method="get" action="<?= escape($url) ?>" data-live-search>
					<input type="hidden" name="tab" value="php" />
					<label class="cms-sr-only" for="system-info-search"><?= escape(__('system-info:search')) ?></label>
					<?= Icon::render('search', 'cms-page-search-icon') ?>
					<input
						id="system-info-search"
						class="cms-input"
						name="q"
						type="search"
						value="<?= escape((string) u($search)) ?>"
						placeholder="<?= escape(__('system-info:search')) ?>" />
				</form>
			<?php else: ?>
				<button type="button" class="cms-button cms-button-secondary cms-button-small cms-system-info-copy" data-copy="system-info-text">
					<?= Icon::render('copy') ?>
					<?= escape(__('system-info:copy')) ?>
				</button>
				<template id="system-info-text"><?= escape((string) u($text)) ?></template>
			<?php endif ?>
		</div>

		<div class="cms-kit-scroll">
			<?php if ($tab === 'php'): ?>
				<?php

				$directives = (array) u($directives);
				$extensions = (array) u($extensions);
				?>
				<?php if ($directives === [] && $extensions === []): ?>
					<?php $this->include('kit/empty', ['title' => __('system-info:no-match')]) ?>
				<?php else: ?>
					<div class="cms-kit-cards">
						<?php if ($extensions !== []): ?>
							<section class="cms-kit-card" aria-labelledby="system-info-extensions">
								<div class="cms-kit-card-head">
									<h2 id="system-info-extensions"><?= escape(__('system-info:group-extensions')) ?></h2>
								</div>
								<table class="cms-system-info-table">
									<thead>
										<tr>
											<th scope="col"><?= escape(__('system-info:extension')) ?></th>
											<th scope="col"><?= escape(__('system-info:version')) ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($extensions as $extension): ?>
											<tr>
												<th scope="row"><?= escape((string) $extension['name']) ?></th>
												<td><?= escape((string) $extension['version']) ?></td>
											</tr>
										<?php endforeach ?>
									</tbody>
								</table>
							</section>
						<?php endif ?>
						<?php if ($directives !== []): ?>
							<section class="cms-kit-card" aria-labelledby="system-info-directives">
								<div class="cms-kit-card-head">
									<h2 id="system-info-directives"><?= escape(__('system-info:directives')) ?></h2>
								</div>
								<table class="cms-system-info-table">
									<thead>
										<tr>
											<th scope="col"><?= escape(__('system-info:directive')) ?></th>
											<th scope="col"><?= escape(__('system-info:local-value')) ?></th>
											<th scope="col"><?= escape(__('system-info:master-value')) ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($directives as $directive): ?>
											<tr>
												<th scope="row"><?= escape((string) $directive['name']) ?></th>
												<td><?= escape((string) ($directive['local'] ?? '')) ?></td>
												<td><?= escape((string) ($directive['master'] ?? '')) ?></td>
											</tr>
										<?php endforeach ?>
									</tbody>
								</table>
							</section>
						<?php endif ?>
					</div>
				<?php endif ?>
			<?php else: ?>
				<?php

				$groups = (array) u($groups);
				$failures = (array) u($failures);
				$warnings = (array) u($warnings);
				$problems = [...$failures, ...$warnings];
				?>
				<div class="cms-kit-cards">
					<?php if ($problems === []): ?>
						<section class="cms-kit-card" data-tone="success" role="status">
							<div class="cms-kit-card-head">
								<h2><?= escape(__('system-info:all-good')) ?></h2>
							</div>
						</section>
					<?php else: ?>
						<section class="cms-kit-card" data-tone="<?= $failures === [] ? 'warning' : 'danger' ?>" aria-labelledby="system-info-problems">
							<div class="cms-kit-card-head">
								<h2 id="system-info-problems"><?= escape(implode(' · ', array_filter([
									$failures === [] ? '' : __n('system-info:problems', 'system-info:problems-plural', count($failures)),
									$warnings === [] ? '' : __n('system-info:warnings', 'system-info:warnings-plural', count($warnings)),
								]))) ?></h2>
							</div>
							<ul class="cms-system-info-problems">
								<?php foreach ($problems as $problem): ?>
									<li>
										<?php $this->include('kit/pill', [
											'label' => $problem['status'] === 'fail' ? __('system-info:status-fail') : __('system-info:status-warn'),
											'tone' => $tones[$problem['status']],
										]) ?>
										<strong><?= escape((string) $problem['label']) ?></strong>
										<?php if (is_string($problem['hint'])): ?>
											<span><?= escape($problem['hint']) ?></span>
										<?php endif ?>
									</li>
								<?php endforeach ?>
							</ul>
						</section>
					<?php endif ?>

					<?php foreach ($groups as $index => $group): ?>
						<section class="cms-kit-card" aria-labelledby="system-info-group-<?= (int) $index ?>">
							<div class="cms-kit-card-head">
								<h2 id="system-info-group-<?= (int) $index ?>"><?= escape((string) $group['title']) ?></h2>
							</div>
							<dl class="cms-system-info-entries">
								<?php foreach ((array) $group['entries'] as $entry): ?>
									<div>
										<dt><?= escape((string) $entry['label']) ?></dt>
										<dd>
											<span class="cms-system-info-value"><?= escape((string) $entry['value']) ?></span>
											<?php if ($entry['status'] === 'ok'): ?>
												<?= Icon::render('check-lg', 'cms-system-info-ok') ?>
												<span class="cms-sr-only"><?= escape(__('system-info:status-ok')) ?></span>
											<?php elseif (isset($tones[$entry['status']])): ?>
												<?php $this->include('kit/pill', [
													'label' => $entry['status'] === 'fail' ? __('system-info:status-fail') : __('system-info:status-warn'),
													'tone' => $tones[$entry['status']],
												]) ?>
											<?php endif ?>
										</dd>
									</div>
								<?php endforeach ?>
							</dl>
						</section>
					<?php endforeach ?>
				</div>
			<?php endif ?>
		</div>
	</div>
</div>
