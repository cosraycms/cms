<?php

use Cosray\Panel\Icon;
use Cosray\Panel\Inspector\Section;

use function Cosray\escape;

// A section a site or a plugin adds to the status tab of the inspector: its
// heading and the content its template renders. The template's controls post
// with the form the editor keeps outside its own, named by `form`, and act at
// once, which the heading says as the visibility switches' does;
// data-editor-state keeps them from marking the editor as edited. A response
// to such a post draws the section again with `oob`.
// Receives: section (a Cosray\Panel\Inspector\Section), oob.

$section = u($section);
assert($section instanceof Section, 'An inspector section is drawn from its value');
$oob = (bool) (u($oob ?? null) ?? false);
?>
<section
	id="cms-inspector-section-<?= escape($section->key) ?>"
	class="cms-inspector-section cms-kit-inspector"
	data-editor-state
	<?= $oob ? 'hx-swap-oob="true"' : '' ?>>
	<div class="cms-kit-inspector-head">
		<h2 class="cms-kit-inspector-heading"><?= escape($section->title) ?></h2>
		<?php if ($section->action !== null): ?>
			<span class="cms-kit-inspector-instant"><?= Icon::render('lightning') ?><?= escape(__('status:instant')) ?></span>
		<?php endif ?>
	</div>
	<?php $this->include($section->template, [...$section->context, 'form' => $section->form()]) ?>
</section>
