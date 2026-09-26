<?php

use Cosray\Panel\AssetFacts;
use Cosray\Panel\Icon;

use function Cosray\escape;

// The inspector's view of the selected file, the region htmx swaps when a
// tile is picked, the detail closes, or its form saves or deletes. Every
// locale's texts render; the content-locales behavior shows the active one.

$screen = $this->unwrap($screen);
$asset = $this->unwrap($asset ?? null);
$usage = (array) $this->unwrap($usage ?? []);
$blocked = $this->unwrap($blocked ?? null);
$saved = (bool) ($saved ?? false);
$locales = (array) $this->unwrap($contentLocales);
$defaultLocale = (string) $defaultLocale;
$locale = (string) ($localeId ?? 'en');
$closeUrl = $screen->url(['file' => null]);
?>
<div id="media-detail" class="cms-media-detail" data-media-detail="<?= escape($asset->uid ?? '') ?>">
<?php if ($asset === null && $screen->file === null): ?>
	<div class="cms-media-inspector-empty"><?= escape(__('media:select-hint')) ?></div>
<?php elseif ($asset === null): ?>
	<div class="cms-detail">
		<div class="cms-detail-status"><?= escape(__('media:file-load-failed')) ?></div>
		<a
			class="cms-button secondary"
			href="<?= escape($closeUrl) ?>"
			hx-get="<?= escape($closeUrl) ?>"
			hx-target="#media-detail"
			hx-swap="outerHTML"
			hx-replace-url="true"><?= escape(__('common:close')) ?></a>
	</div>
<?php else: ?>
	<?php
	$image = $asset->kind === 'image';
	$meta = $asset->meta;
	$focal = is_array($meta['focal'] ?? null) ? $meta['focal'] : null;
	$saveUrl = $screen->url(below: rawurlencode($asset->uid));
	$deleteUrl = $screen->url(below: rawurlencode($asset->uid) . '/delete');
	$texts = [
		'alt' => __('image:alt-text-long'),
		'title' => __('common:title'),
		'caption' => __('image:caption'),
	];

	// Alt text describes image content; other kinds have none.
	if (!$image) {
		unset($texts['alt']);
	}
	?>
	<form
		class="cms-detail"
		method="post"
		action="<?= escape($saveUrl) ?>"
		hx-post="<?= escape($saveUrl) ?>"
		hx-target="#media-detail"
		hx-swap="outerHTML">
		<header class="cms-detail-head">
			<h2 title="<?= escape($asset->filename) ?>"><?= escape($asset->filename) ?></h2>
			<a
				class="cms-detail-close"
				href="<?= escape($closeUrl) ?>"
				hx-get="<?= escape($closeUrl) ?>"
				hx-target="#media-detail"
				hx-swap="outerHTML"
				hx-replace-url="true"
				aria-label="<?= escape(__('common:close')) ?>"><?= Icon::render('x-lg') ?></a>
		</header>

		<div class="cms-detail-body">
			<?php if ($image): ?>
				<?php // A click sets the focal point; keyboard activation has no position and leaves it. ?>
				<button
					type="button"
					class="cms-detail-preview focusable"
					title="<?= escape(__('image:set-focus-hint')) ?>"
					data-media-focal>
					<img src="<?= escape($asset->resizable() ? $asset->sizePath('preview') : $asset->path()) ?>" alt="<?= escape($asset->filename) ?>" />
					<span
						class="cms-detail-focal"
						style="left: <?= $focal ? $focal['x'] * 100 : 50 ?>%; top: <?= $focal ? $focal['y'] * 100 : 50 ?>%"
						<?= $focal ? '' : 'hidden' ?>
						data-media-focal-marker></span>
				</button>
			<?php else: ?>
				<div class="cms-detail-preview">
					<span class="cms-detail-preview-icon"><?= Icon::render('file-earmark-richtext') ?></span>
				</div>
			<?php endif ?>

			<dl class="cms-detail-meta">
				<div>
					<dt><?= escape(__('common:type')) ?></dt>
					<dd><?= escape($asset->mime ?? $asset->kind) ?></dd>
				</div>
				<?php if ($asset->width && $asset->height): ?>
					<div>
						<dt><?= escape(__('image:size')) ?></dt>
						<dd><?= escape("{$asset->width} × {$asset->height} px") ?></dd>
					</div>
				<?php endif ?>
				<div>
					<dt><?= escape(__('media:file-size')) ?></dt>
					<dd><?= escape($asset->bytes === null ? '—' : AssetFacts::size($asset->bytes, $locale)) ?></dd>
				</div>
				<div>
					<dt><?= escape(__('image:original')) ?></dt>
					<dd><a href="<?= escape($asset->path()) ?>" target="_blank" rel="noopener" hx-boost="false"><?= escape(
						__('common:open'),
					) ?></a></dd>
				</div>
			</dl>

			<?php if ($image): ?>
				<div class="cms-detail-focal-controls">
					<span
						data-media-focal-text
						data-label="<?= escape(__('image:focus')) ?>"
						data-empty="<?= escape(__('image:no-focus')) ?>"><?= escape(
						$focal
							? __('image:focus') . ': ' . round($focal['x'] * 100) . '% / ' . round($focal['y'] * 100) . '%'
							: __('image:no-focus'),
					) ?></span>
					<button
						type="button"
						class="cms-button secondary small"
						<?= $focal ? '' : 'hidden' ?>
						data-media-focal-clear><?= escape(__('image:focus-remove')) ?></button>
					<input type="hidden" name="meta[focal][x]" value="<?= escape((string) ($focal['x'] ?? '')) ?>" data-media-focal-x />
					<input type="hidden" name="meta[focal][y]" value="<?= escape((string) ($focal['y'] ?? '')) ?>" data-media-focal-y />
				</div>
			<?php endif ?>

			<div class="cms-meta-form">
				<?php foreach ($texts as $key => $label): ?>
					<?php $id = "media-meta-{$key}" ?>
					<div class="cms-meta-field">
						<label for="<?= escape("{$id}-{$defaultLocale}") ?>" data-locale-label-for="<?= escape($id) ?>"><?= escape(
							$label,
						) ?></label>
						<?php foreach ($locales as $entry): ?>
							<?php
							$value = (string) ($meta[$key][$entry['id']] ?? '');
							$name = "meta[{$key}][{$entry['id']}]";
							?>
							<div class="variant" data-locale="<?= escape($entry['id']) ?>" <?= $entry['id'] === $defaultLocale ? '' : 'hidden' ?>>
								<?php if ($key === 'caption'): ?>
									<textarea class="cms-input" rows="2" id="<?= escape("{$id}-{$entry['id']}") ?>" name="<?= escape($name) ?>"><?= escape($value) ?></textarea>
								<?php else: ?>
									<input class="cms-input" type="text" id="<?= escape("{$id}-{$entry['id']}") ?>" name="<?= escape($name) ?>" value="<?= escape($value) ?>" />
								<?php endif ?>
							</div>
						<?php endforeach ?>
					</div>
				<?php endforeach ?>
				<div class="cms-meta-field">
					<label for="media-meta-credit"><?= escape(__('image:credit')) ?></label>
					<input
						class="cms-input"
						type="text"
						id="media-meta-credit"
						name="meta[credit]"
						value="<?= escape(is_string($meta['credit'] ?? null) ? $meta['credit'] : '') ?>" />
				</div>
			</div>

			<section class="cms-detail-usage">
				<h3><?= escape(__('media:usage')) ?></h3>
				<?php if ($usage === []): ?>
					<p class="cms-detail-hint"><?= escape(__('media:unused')) ?></p>
				<?php else: ?>
					<ul>
						<?php foreach ($usage as $owner): ?>
							<li>
								<span class="cms-detail-usage-title"><?= escape($owner['title'] ?: $owner['ownerUid']) ?></span>
								<span class="cms-detail-usage-kind">
									<?= escape($owner['nodeType'] ?? $owner['ownerType']) ?>
									<?php if ($owner['ownerType'] === 'draft'): ?>
										· <?= escape(__('media:usage-changes')) ?>
									<?php elseif ($owner['published'] === false): ?>
										· <?= escape(__('node:unpublished')) ?>
									<?php endif ?>
								</span>
							</li>
						<?php endforeach ?>
					</ul>
				<?php endif ?>
			</section>
		</div>

		<footer class="cms-detail-foot">
			<button type="button" class="cms-button danger" data-media-delete>
				<?= Icon::render('trash3') ?>
				<?= escape(__('common:delete')) ?>
			</button>
			<div class="cms-detail-foot-right">
				<?php if ($saved): ?>
					<span class="cms-detail-saved" role="status"><?= escape(__('common:saved')) ?></span>
				<?php endif ?>
				<button type="submit" class="cms-button primary">
					<?= Icon::render('floppy') ?>
					<?= escape(__('common:save')) ?>
				</button>
			</div>
		</footer>

		<?php if (is_array($blocked)): ?>
			<div class="cms-detail-blocked" role="alert">
				<p><?= escape(__('media:delete-in-use')) ?></p>
				<ul>
					<?php foreach ($blocked as $owner): ?>
						<li><?= escape(($owner['title'] ?: $owner['ownerUid']) . ' (' . ($owner['nodeType'] ?? $owner['ownerType']) . ')') ?></li>
					<?php endforeach ?>
				</ul>
			</div>
		<?php endif ?>
	</form>

	<dialog class="cms-modal" data-size="compact" data-media-delete-dialog>
		<?php $this->insert('component/modal-header', ['title' => __('media:delete')]) ?>
		<div class="modal-body cms-confirm">
			<p class="question"><?= escape(__('media:confirm-delete')) ?></p>
			<p class="cms-modal-remove-message"><?= escape($asset->filename) ?></p>
		</div>
		<footer class="modal-footer">
			<button type="button" class="cms-button secondary" data-dialog-close data-dialog-focus><?= escape(
				__('media:cancel-delete-file'),
			) ?></button>
			<form method="post" action="<?= escape($deleteUrl) ?>" hx-post="<?= escape($deleteUrl) ?>" hx-target="#media-detail" hx-swap="outerHTML">
				<button type="submit" class="cms-button danger solid"><?= escape(__('media:confirm-delete-file')) ?></button>
			</form>
		</footer>
	</dialog>
<?php endif ?>
</div>
