<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The media screen. htmx asks for one region at a time: the inspector's
// detail, or the next page of tiles; everything else renders the page.

$part = (string) ($part ?? 'page');

if ($part === 'detail') {
	$this->insert('media/detail');

	return;
}

if ($part === 'tiles') {
	$this->insert('media/tiles');

	return;
}

$this->layout('layer/main');

$screen = $this->unwrap($screen);
$listing = $this->unwrap($listing);
$system = (array) $this->unwrap($system);
$contentLocales = (array) $this->unwrap($contentLocales);
$defaultLocale = (string) $defaultLocale;
$collapsed = (bool) $inspectorCollapsed;
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$search = $screen->query(['q' => '']);
unset($search['page']);
?>

<div
	class="page cms-media"
	data-content-locale-scope
	data-content-locale="<?= escape($defaultLocale) ?>"
	data-media>
	<header class="head">
		<div class="titles">
			<div class="line">
				<h1><?= escape(__('media:title')) ?></h1>
				<span class="cms-count"><?= escape(__('media:file-count', ['count' => $listing->total])) ?></span>
			</div>
		</div>
		<div class="actions">
			<div class="cms-media-toolbar">
				<button type="button" class="cms-button primary" data-media-upload>
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
				<div class="toolbar">
					<form class="search" method="get" action="<?= escape($screen->path) ?>">
						<?php foreach ($search as $name => $value): ?>
							<input type="hidden" name="<?= escape($name) ?>" value="<?= escape($value) ?>" />
						<?php endforeach ?>
						<span class="icon" aria-hidden="true">⌕</span>
						<input
							class="cms-input"
							type="search"
							name="q"
							value="<?= escape($screen->q) ?>"
							aria-label="<?= escape(__('media:search-filename')) ?>"
							placeholder="<?= escape(__('media:search-filename')) ?>" />
					</form>
					<?php if ($screen->q !== ''): ?>
						<a class="cms-button secondary" href="<?= escape($screen->url(['q' => ''])) ?>"><?= escape(
							__('common:reset'),
						) ?></a>
					<?php endif ?>
				</div>

				<div class="cms-media-scroll">
					<?php if ($listing->assets === []): ?>
						<div class="cms-media-empty"><?= escape(__('media:no-files')) ?></div>
					<?php else: ?>
						<div class="cms-asset-grid" data-media-grid>
							<?php $this->insert('media/tiles') ?>
						</div>
					<?php endif ?>
				</div>

				<div class="drop" aria-hidden="true">
					<?= Icon::render('cloud-upload') ?>
					<?= escape(__('media:drop-to-upload')) ?>
				</div>
			</section>

			<aside
				class="cms-inspector"
				aria-label="<?= escape(__('media:file-details')) ?>"
				data-inspector
				<?= $collapsed ? 'data-collapsed' : '' ?>>
				<div class="strip">
					<button
						type="button"
						class="tool"
						title="<?= escape(__('media:details-show')) ?>"
						aria-label="<?= escape(__('media:details-show')) ?>"
						data-inspector-expand>
						<?= Icon::render('layout-sidebar-inset-reverse') ?>
					</button>
					<?php $this->insert('component/content-locales', [
						'locales' => $contentLocales,
						'selected' => $defaultLocale,
						'controlId' => 'cms-media-locale-strip',
						'strip' => true,
					]) ?>
				</div>
				<div class="drawer">
					<div class="top">
						<span class="heading"><?= escape(__('media:file-details')) ?></span>
						<button
							type="button"
							class="tool"
							title="<?= escape(__('media:details-hide')) ?>"
							aria-label="<?= escape(__('media:details-hide')) ?>"
							data-inspector-collapse>
							<?= Icon::render('layout-sidebar-inset-reverse') ?>
						</button>
					</div>
					<div class="scroll">
						<?php $this->insert('component/content-locales', [
							'locales' => $contentLocales,
							'selected' => $defaultLocale,
							'controlId' => 'cms-media-locale',
						]) ?>
						<?php $this->insert('media/detail', (array) $this->unwrap($detail ?? [])) ?>
					</div>
				</div>
			</aside>
		</div>
	</section>

	<script id="cosray-system-data" type="application/json"><?= json_encode(
		['panel' => (string) $panelBase, 'system' => $system],
		$jsonFlags,
	) ?></script>
</div>
