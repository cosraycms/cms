<?php if (!$boosted)
	$this->layout('app'); ?>

<section class="collection-page">
	<header>
		<h1 class="collection-title"><?= $name ?></h1>
	</header>

	<?php if ($nodes === []): ?>
		<p class="collection-empty">No entries found.</p>
	<?php else: ?>
		<ul class="collection-list">
			<?php foreach ($nodes as $node): ?>
				<li class="collection-row" data-uid="<?= $node['uid'] ?>">
					<div class="collection-grid">
						<?php foreach ($node['columns'] as $index => $column): ?>
							<?php

							$label = $header[$index] ?? 'Column ' . ((int) $index + 1);
							$value = $column['value'] ?? '';

							if (($column['date'] ?? false) && is_string($value) && $value !== '') {
								try {
									$value = new DateTimeImmutable($value)->format('d.m.Y H:i');
								} catch (Throwable) {
									// Keep original value when it cannot be parsed as datetime.
								}
							}

							if (is_bool($value)) {
								$value = $value ? 'Yes' : 'No';
							}

							if (is_scalar($value)) {
								$display = (string) $value;
							} elseif (is_object($value) && method_exists($value, '__toString')) {
								$display = (string) $value;
							} else {
								$display = '';
							}

							$classes = ['collection-cell'];

							if (($column['bold'] ?? false) === true) {
								$classes[] = 'is-bold';
							}

							if (($column['italic'] ?? false) === true) {
								$classes[] = 'is-italic';
							}
							?>
							<div class="<?= implode(' ', $classes) ?>">
								<div class="collection-label"><?= $label ?></div>
								<div class="collection-value"><?= $display ?></div>
							</div>
						<?php endforeach ?>
					</div>
					<a class="collection-edit" href="<?= $panelPath ?>/node/<?= $node['uid'] ?>" hx-boost="true" hx-target="#main">Edit</a>
				</li>
			<?php endforeach ?>
		</ul>
	<?php endif ?>
</section>
