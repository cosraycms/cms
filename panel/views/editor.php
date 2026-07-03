<?php

use function Cosray\escape;

if (!$boosted) {
	$this->layout('panel');
}

$mode = (string) $mode;
$name = (string) $name;
$slug = (string) $slug;
$node = (array) $this->unwrap($node);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$fields = $node['fields'] ?? [];
$content = $node['content'] ?? [];
$type = $node['type'] ?? [];
$uid = (string) ($node['uid'] ?? '');
$action = $mode === 'create'
	? $links->create((string) ($type['handle'] ?? ''))
	: $links->edit($uid);

$span = static function (mixed $value, int $fallback): string {
	$value = is_int($value) ? $value : $fallback;

	if ($value > 100 || $value <= 0) {
		$value = 100;
	}

	return "span {$value} / span {$value}";
};
?>

<div id="main" class="page node">
	<section class="editor-content">
		<div class="cms-node-shell">
			<div class="cms-node-sticky">
				<header class="cms-node-topbar">
					<div class="inner">
						<div class="trail">
							<a class="cms-breadcrumb" href="<?= escape($links->back()) ?>">
								<?= escape($name) ?>
							</a>
						</div>
						<div class="actions">
							<output id="editor-status" class="editor-status" role="status"></output>
							<?php if ($mode === 'edit'): ?>
								<button class="cms-button primary" type="submit" form="node-editor-form">
									<?= escape(_('Speichern')) ?>
								</button>
							<?php endif ?>
						</div>
					</div>
				</header>
				<div class="cms-node-header-frame">
					<header class="cms-node-header">
						<h1 class="cms-headline">
							<span class="cms-headline-title"><?= $node['title'] ?? '' ?></span>
						</h1>
					</header>
				</div>
			</div>
			<div class="cms-document">
				<div class="cms-document-inner">
					<div class="cms-pane">
						<div class="cms-pane-card">
							<div id="editor-errors" class="editor-errors" hidden></div>
							<form
								id="node-editor-form"
								class="cms-node-form"
								method="post"
								action="<?= escape($action) ?>"
								hx-swap="none">
								<div class="field-grid">
									<?php foreach ($fields as $field): ?>
										<?php if ($field['hidden'] ?? false) {
											continue;
										} ?>
										<div style="
											grid-column: <?= $span($field['width'] ?? null, 100) ?>;
											grid-row: <?= $span($field['rows'] ?? null, 1) ?>">
											<?php $this->insert('field/field', [
												'field' => $field,
												'data' => $content[$field['name']] ?? null,
												'locales' => $locales,
												'defaultLocale' => $defaultLocale,
											]) ?>
										</div>
									<?php endforeach ?>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
