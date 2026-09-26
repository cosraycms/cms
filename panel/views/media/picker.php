<?php

use function Cosray\escape;

// The library as a control embeds it: a search and the tile grid. The search
// swaps the whole picker; a picked tile's asset goes to the embedding script.

$screen = $this->unwrap($screen);
$listing = $this->unwrap($listing);
$search = $screen->query(['q' => '']);
?>
<div class="cms-library" data-media-picker>
	<form
		class="cms-library-search"
		method="get"
		action="<?= escape($screen->path) ?>"
		hx-get="<?= escape($screen->path) ?>"
		hx-target="closest [data-media-picker]"
		hx-swap="outerHTML">
		<?php foreach ($search as $name => $value): ?>
			<input type="hidden" name="<?= escape($name) ?>" value="<?= escape($value) ?>" />
		<?php endforeach ?>
		<input
			class="cms-input"
			type="search"
			name="q"
			value="<?= escape($screen->q) ?>"
			aria-label="<?= escape(__('media:search-filename')) ?>"
			placeholder="<?= escape(__('media:search-filename')) ?>" />
		<button type="submit" class="cms-button secondary"><?= escape(__('common:search')) ?></button>
	</form>
	<?php if ($listing->assets === []): ?>
		<div class="cms-library-empty"><?= escape(__('media:no-files')) ?></div>
	<?php else: ?>
		<div class="cms-library-grid">
			<div class="cms-asset-grid"><?php $this->insert('media/tiles') ?></div>
		</div>
	<?php endif ?>
</div>
