<?php

use Cosray\Assets\Library;
use Cosray\Panel\AssetFacts;

use function Cosray\escape;

// The tiles of one listing page, then the link to the next. That link is the
// grid's last cell: htmx swaps it for the next page's tiles and their own
// successor, and without htmx it simply pages. The media screen's tiles
// open the detail; a picker's are buttons carrying their asset.

$screen = $this->unwrap($screen);
$listing = $this->unwrap($listing);
$picker = (bool) ($picker ?? false);
$locale = (string) ($localeId ?? 'en');
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<?php foreach ($listing->assets as $asset): ?>
	<?php $active = $asset->uid === $screen->file ? ' active' : '' ?>
	<?php if ($picker): ?>
	<button
		type="button"
		class="cms-asset-tile<?= $active ?>"
		title="<?= escape($asset->filename) ?>"
		data-pick="<?= escape((string) json_encode(Library::item($asset), $jsonFlags)) ?>">
	<?php else: ?>
	<?php $url = $screen->url(['file' => $asset->uid]) ?>
	<a
		class="cms-asset-tile<?= $active ?>"
		href="<?= escape($url) ?>"
		title="<?= escape($asset->filename) ?>"
		hx-get="<?= escape($url) ?>"
		hx-target="#media-detail"
		hx-swap="outerHTML"
		hx-replace-url="true"
		data-media-tile="<?= escape($asset->uid) ?>">
	<?php endif ?>
		<span class="cms-asset-thumb">
			<?php if ($asset->kind === 'image'): ?>
				<img src="<?= escape($asset->resizable() ? $asset->sizePath('thumb') : $asset->path()) ?>" alt="" loading="lazy" />
			<?php else: ?>
				<span class="cms-asset-ext"><?= escape(AssetFacts::extension($asset->filename) ?: $asset->kind) ?></span>
			<?php endif ?>
		</span>
		<span class="cms-asset-meta">
			<span class="cms-asset-name"><?= escape($asset->filename) ?></span>
			<?php $line = AssetFacts::line($asset, $locale) ?>
			<?php if ($line !== ''): ?>
				<span class="cms-asset-line"><?= escape($line) ?></span>
			<?php endif ?>
		</span>
	<?= $picker ? '</button>' : '</a>' ?>
<?php endforeach ?>
<?php if ($listing->more): ?>
	<?php $next = $screen->url(['file' => $screen->file, 'page' => $listing->page + 1]) ?>
	<a
		id="media-more"
		class="cms-button secondary cms-media-more"
		href="<?= escape($next) ?>"
		hx-get="<?= escape($next) ?>"
		hx-target="this"
		hx-swap="outerHTML"
		hx-push-url="false"><?= escape(__('common:load-more')) ?></a>
<?php endif ?>
