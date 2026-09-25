<?php

use function Cosray\escape;

$field = (array) $this->unwrap($field);
$value = $this->unwrap($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
$video = preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1 ? $value : '';
$readonly = (bool) ($field['immutable'] ?? false);
$placeholder = (string) ($field['placeholder'] ?? __('youtube:placeholder'));
$data = $this->unwrap($data ?? null);
$meta = is_array($data) && is_array($data['meta'] ?? null) ? $data['meta'] : [];
$side = static fn(string $key, int $default): int => is_array($meta[$key] ?? null)
	&& is_numeric($meta[$key]['zxx'] ?? null)
	&& (int) $meta[$key]['zxx'] > 0
		? (int) $meta[$key]['zxx']
		: $default;
$ratio = $side('aspectRatioX', 16) . ' / ' . $side('aspectRatioY', 9);
?>
<div class="cms-youtube" data-youtube style="--ratio: <?= $ratio ?>">
	<?php // The unnamed editor holds a draft; only this committed value participates in saves and duplication. ?>
	<input
		type="hidden"
		data-youtube-value
		<?= $fallbackPreview ?? false ? 'data-fallback-input data-schema-placeholder="' . escape($placeholder) . '"' : '' ?>
		name="<?= escape($name) ?>"
		value="<?= escape($value) ?>" />
	<iframe
		class="player"
		data-youtube-player
		title="<?= escape(__('block:youtube')) ?>"
		loading="lazy"
		referrerpolicy="strict-origin-when-cross-origin"
		allow="fullscreen; encrypted-media; picture-in-picture"
		<?= $video !== '' ? 'src="' . escape("https://www.youtube-nocookie.com/embed/{$video}") . '"' : 'hidden' ?>></iframe>
	<div class="entry" data-youtube-entry data-editor-state <?= $video !== '' ? 'hidden' : '' ?>>
		<input
			class="cms-input"
			type="text"
			id="<?= escape($id) ?>"
			data-youtube-input
			<?= $fallbackPreview ?? false ? 'data-fallback-editor' : '' ?>
			value="<?= escape($value) ?>"
			placeholder="<?= escape($placeholder) ?>"
			aria-label="<?= escape((string) ($field['label'] ?? __('block:youtube'))) ?>"
			<?= !$readonly && ($field['required'] ?? false) ? 'aria-required="true"' : '' ?>
			autocomplete="off"
			spellcheck="false"
			<?= $readonly ? 'readonly' : '' ?> />
		<?php if (!$readonly): ?>
			<button type="button" class="cms-button secondary small" data-youtube-add <?= trim($value) === '' ? 'hidden' : '' ?>>
				<?= escape(__('youtube:add')) ?>
			</button>
			<button type="button" class="cms-button secondary small" data-youtube-confirm hidden>
				<?= escape(__('youtube:replace')) ?>
			</button>
			<button type="button" class="cms-button quiet small" data-youtube-cancel hidden>
				<?= escape(__('common:cancel')) ?>
			</button>
			<?php if (!($field['required'] ?? false)): ?>
				<button type="button" class="cms-button quiet small" data-youtube-remove hidden>
					<?= escape(__('field:remove')) ?>
				</button>
			<?php endif ?>
		<?php endif ?>
	</div>
	<p class="cms-field-error" id="<?= escape($id) ?>-youtube-error" data-youtube-error role="alert" hidden>
		<?= escape(__('youtube:invalid')) ?>
	</p>
	<?php if (!$readonly): ?>
		<button type="button" class="cms-button quiet small replace" data-youtube-replace <?= $video === '' ? 'hidden' : '' ?>>
			<?= escape(__('youtube:replace')) ?>
		</button>
	<?php endif ?>
</div>
