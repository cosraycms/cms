<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The media screen. htmx asks for one region at a time: the inspector's
// detail, or the next page of tiles; everything else renders the page.

$part = (string) ($part ?? 'page');

if ($part === 'detail') {
	$this->include('media/detail');

	return;
}

if ($part === 'tiles') {
	$this->include('media/tiles');

	return;
}

if ($part === 'picker') {
	$this->include('media/picker');

	return;
}

$this->layout('layer/main');

$screen = u($screen);
$listing = u($listing);
$system = (array) u($system);
$contentLocales = (array) u($contentLocales);
$defaultLocale = (string) $defaultLocale;
$collapsed = u($panelCookie)->is('inspector', 'collapsed');
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$search = $screen->query(['q' => '']);
unset($search['page']);
?>

<div
	class="cms-page cms-media"
	data-content-locale-scope
	data-content-locale="<?= escape($defaultLocale) ?>"
	data-media>
	<header class="cms-page-head">
		<div class="cms-page-titles">
			<div class="cms-page-line">
				<h1><?= escape(__('media:title')) ?></h1>
				<span class="cms-count"><?= escape(__n('media:file-count', 'media:file-count-plural', $listing->total)) ?></span>
			</div>
		</div>
		<div class="cms-page-actions">
			<div class="cms-media-toolbar">
				<button type="button" class="cms-button cms-button-primary" data-media-upload>
					<?= Icon::render('cloud-upload') ?>
					<span data-media-upload-label><?= escape(__('common:upload')) ?></span>
				</button>
				<input type="file" multiple hidden data-media-upload-input />
			</div>
		</div>
	</header>

	<section class="body">
		<div class="cms-media-workspace">
			<section class="cms-media-pane cms-dropzone" aria-label="<?= escape(__('media:title')) ?>" data-media-drop>
				<div class="cms-page-toolbar">
					<form class="cms-page-search" method="get" action="<?= escape($screen->path) ?>" data-media-keep-file>
						<?php foreach ($search as $name => $value): ?>
							<input type="hidden" name="<?= escape($name) ?>" value="<?= escape($value) ?>" />
						<?php endforeach ?>
						<span class="cms-page-search-icon" aria-hidden="true">⌕</span>
						<input
							class="cms-input"
							type="search"
							name="q"
							value="<?= escape($screen->q) ?>"
							aria-label="<?= escape(__('media:search')) ?>"
							placeholder="<?= escape(__('media:search')) ?>" />
					</form>
					<?php if ($screen->q !== ''): ?>
						<a class="cms-button cms-button-secondary" href="<?= escape($screen->url(['q' => ''])) ?>" data-media-keep-file><?= escape(
							__('common:reset'),
						) ?></a>
					<?php endif ?>
				</div>

				<div class="selection" data-media-selection hidden>
					<span role="status" data-media-selection-count></span>
					<button
						type="button"
						class="cms-button cms-button-danger cms-button-small"
						data-media-delete-selected
						data-url="<?= escape($screen->path . '/delete-many') ?>"
						data-confirm-title="<?= escape(__('media:delete-selected')) ?>"
						data-confirm-action="<?= escape(__('common:delete')) ?>"
						data-confirm-tone="danger">
						<?= Icon::render('trash3') ?>
						<?= escape(__('media:delete-selected')) ?>
					</button>
					<button type="button" class="cms-button cms-button-secondary cms-button-small" data-media-selection-clear>
						<?= escape(__('media:clear-selection')) ?>
					</button>
				</div>

				<div class="cms-media-scroll">
					<?php if ($listing->assets === []): ?>
						<div class="cms-media-empty"><?= escape(__('media:no-files')) ?></div>
					<?php else: ?>
						<div class="cms-asset-grid" data-media-grid>
							<?php $this->include('media/tiles') ?>
						</div>
					<?php endif ?>
				</div>

				<div class="cms-dropzone-label" aria-hidden="true">
					<?= Icon::render('cloud-upload') ?>
					<?= escape(__('media:drop-to-upload')) ?>
				</div>
			</section>

			<aside
				class="cms-inspector"
				aria-label="<?= escape(__('media:file-details')) ?>"
				data-inspector
				<?= $collapsed ? 'data-collapsed' : '' ?>>
				<div class="cms-inspector-strip">
					<button
						type="button"
						class="cms-inspector-tool"
						title="<?= escape(__('media:details-show')) ?>"
						aria-label="<?= escape(__('media:details-show')) ?>"
						data-inspector-expand>
						<?= Icon::render('layout-sidebar-inset-reverse') ?>
					</button>
					<?php $this->include('component/content-locales', [
						'locales' => $contentLocales,
						'selected' => $defaultLocale,
						'controlId' => 'cms-media-locale-strip',
						'strip' => true,
					]) ?>
				</div>
				<div class="cms-inspector-drawer">
					<div class="cms-inspector-top">
						<span class="cms-inspector-heading"><?= escape(__('media:file-details')) ?></span>
						<button
							type="button"
							class="cms-inspector-tool"
							title="<?= escape(__('media:details-hide')) ?>"
							aria-label="<?= escape(__('media:details-hide')) ?>"
							data-inspector-collapse>
							<?= Icon::render('layout-sidebar-inset-reverse') ?>
						</button>
					</div>
					<div class="cms-inspector-scroll">
						<?php $this->include('component/content-locales', [
							'locales' => $contentLocales,
							'selected' => $defaultLocale,
							'controlId' => 'cms-media-locale',
						]) ?>
						<?php $this->include('media/detail', (array) u($detail ?? [])) ?>
					</div>
				</div>
			</aside>
		</div>
	</section>

	<script id="cosray-system-data" type="application/json"><?= json_encode(
		['panel' => (string) u($panelBase), 'system' => $system],
		$jsonFlags,
	) ?></script>
</div>
