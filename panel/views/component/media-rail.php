<?php

use Cosray\Assets\Library;

use function Cosray\escape;

// The library's filters in the shell's rail. Changing one reloads the
// listing into the content region; the main layer patches the rail back in
// out of band, so its counts follow the search.

$oob = (bool) ($oob ?? false);

if (!$rail) {
	return;
}

$screen = $this->unwrap($screen ?? null);
$listing = $this->unwrap($listing ?? null);
?>
<div id="media-rail"<?= $oob ? ' hx-swap-oob="true"' : '' ?> data-media-rail>
<?php if ($screen !== null && $listing !== null): ?>
	<?php
	$kindLabels = [
		'image' => __('media:images'),
		'video' => __('media:videos'),
		'audio' => __('media:audio'),
		'document' => __('media:documents'),
	];
	$ranges = [
		'' => __('media:date-any'),
		'7d' => __('media:date-7d'),
		'30d' => __('media:date-30d'),
		'year' => __('media:date-year'),
	];
	?>
	<form
		class="cms-media-rail"
		method="get"
		action="<?= escape($screen->path) ?>"
		hx-get="<?= escape($screen->path) ?>"
		hx-trigger="change"
		hx-target="#main"
		hx-push-url="true">
		<?php if ($screen->q !== ''): ?>
			<input type="hidden" name="q" value="<?= escape($screen->q) ?>" />
		<?php endif ?>
		<?php if ($screen->file !== null): ?>
			<input type="hidden" name="file" value="<?= escape($screen->file) ?>" />
		<?php endif ?>
		<div class="cms-media-rail-head">
			<span class="cms-media-rail-title"><?= escape(__('common:filter')) ?></span>
			<?php if ($screen->filtered()): ?>
				<a
					class="cms-media-reset"
					href="<?= escape($screen->url(['kind' => [], 'q' => '', 'range' => ''])) ?>"
					hx-target="#main"><?= escape(__('common:reset')) ?></a>
			<?php endif ?>
		</div>

		<fieldset class="cms-media-rail-group">
			<legend class="cms-media-rail-title"><?= escape(__('common:type')) ?></legend>
			<?php foreach (Library::KINDS as $kind): ?>
				<label class="cms-media-check">
					<input
						type="checkbox"
						name="kind[]"
						value="<?= escape($kind) ?>"
						<?= in_array($kind, $screen->kinds, true) ? 'checked' : '' ?> />
					<span class="cms-media-check-label"><?= escape($kindLabels[$kind]) ?></span>
					<span class="cms-media-check-count"><?= (int) ($listing->counts[$kind] ?? 0) ?></span>
				</label>
			<?php endforeach ?>
		</fieldset>

		<fieldset class="cms-media-rail-group">
			<legend class="cms-media-rail-title"><?= escape(__('media:uploaded')) ?></legend>
			<?php foreach ($ranges as $value => $label): ?>
				<label class="cms-media-check">
					<input
						type="radio"
						name="range"
						value="<?= escape((string) $value) ?>"
						<?= $screen->range === (string) $value ? 'checked' : '' ?> />
					<span class="cms-media-check-label"><?= escape($label) ?></span>
				</label>
			<?php endforeach ?>
		</fieldset>
	</form>
<?php endif ?>
</div>
