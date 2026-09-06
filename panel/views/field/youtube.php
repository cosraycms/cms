<?php

use function Cosray\escape;

// A YouTube id with the video's thumbnail: the preview shows once the
// input holds an id and follows it as it is typed; the youtube behavior
// reduces a pasted YouTube URL to its id. The thumbnail comes from
// YouTube's image host, fetched by the editor's browser.

$field = (array) $this->unwrap($field);
$value = $this->unwrap($value ?? '');
$value = is_scalar($value) ? (string) $value : '';
$video = preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1 ? $value : '';
?>
<div class="cms-youtube" data-youtube>
	<img
		class="thumbnail"
		data-youtube-preview
		alt=""
		<?= $video !== '' ? 'src="' . escape("https://i.ytimg.com/vi/{$video}/hqdefault.jpg") . '"' : 'hidden' ?> />
	<?php $this->insert('field/input', [
		'field' => $field,
		'id' => $id,
		'name' => $name,
		'value' => $value,
		'type' => 'text',
		'fallbackPreview' => $fallbackPreview ?? false,
		'attrs' => [
			'placeholder' => $field['placeholder'] ?? null,
			'autocomplete' => 'off',
			'spellcheck' => 'false',
		],
	]) ?>
</div>
