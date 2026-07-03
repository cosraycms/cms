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
$system = (array) $this->unwrap($system);
$panelPath = (string) $panelPath;
$panelBase = $panelPath === '/' ? '/' : rtrim($panelPath, '/') . '/';
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

$fields = $node['fields'] ?? [];
$content = $node['content'] ?? [];
$type = $node['type'] ?? [];
$uid = (string) ($node['uid'] ?? '');
$routable = (bool) ($type['routable'] ?? false);
$renderable = (bool) ($type['renderable'] ?? false);
$published = (bool) ($node['published'] ?? false);
$deletable = (bool) ($node['deletable'] ?? false);
$showSettings = $routable || $renderable;
$edit = $mode === 'edit';
$action = $edit
	? $links->edit($uid)
	: $links->create((string) ($type['handle'] ?? ''));

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
							<?php if ($edit && $deletable): ?>
								<form
									method="post"
									action="<?= escape($links->delete($uid)) ?>"
									hx-confirm="<?= escape(_('Dieses Dokument wirklich löschen?')) ?>">
									<button class="cms-button danger" type="submit">
										<?= escape(_('Löschen')) ?>
									</button>
								</form>
							<?php endif ?>
							<?php if ($edit && $routable && $renderable): ?>
								<button
									class="cms-button secondary"
									type="submit"
									form="node-editor-form"
									name="preview"
									value="1">
									<?= escape(_('Vorschau')) ?>
								</button>
							<?php endif ?>
							<button
								class="cms-button secondary"
								type="submit"
								form="node-editor-form"
								name="publish"
								value="1">
								<?= escape(_('Speichern und veröffentlichen')) ?>
							</button>
							<button class="cms-button primary" type="submit" form="node-editor-form">
								<?= escape(_('Speichern')) ?>
							</button>
						</div>
					</div>
				</header>
				<div class="cms-node-header-frame">
					<header class="cms-node-header">
						<h1 class="cms-headline">
							<span class="cms-headline-title"><?= $node['title'] ?? '' ?></span>
							<div class="status-bar cms-headline-status">
								<?php if ($renderable): ?>
									<span class="cms-headline-published">
										<span
											id="editor-published"
											class="cms-published large<?= $published ? ' published' : '' ?>">
											<?= escape($published ? _('veröffentlicht') : _('unveröffentlicht')) ?>
										</span>
									</span>
								<?php endif ?>
							</div>
						</h1>
						<?php if ($showSettings): ?>
							<div class="tabs cms-tabs">
								<nav>
									<button type="button" class="tab active" data-pane-tab="content">
										<?= escape(_('Inhalt')) ?>
									</button>
									<button type="button" class="tab" data-pane-tab="settings">
										<?= escape(_('Einstellungen')) ?>
									</button>
								</nav>
							</div>
						<?php endif ?>
					</header>
				</div>
			</div>
			<div class="cms-document">
				<div class="cms-document-inner">
					<div id="editor-errors" class="editor-errors" hidden></div>
					<?php // novalidate: the form legitimately hides controls (locale

					// variants, panes, meta dialogs), which native validation cannot
					// handle; the server validates and reports out-of-band. ?>
					<form
						id="node-editor-form"
						class="cms-node-form"
						method="post"
						action="<?= escape($action) ?>"
						hx-swap="none"
						novalidate>
						<div class="cms-pane" data-pane="content">
							<div class="cms-pane-card">
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
												'node' => $edit ? $uid : '',
											]) ?>
										</div>
									<?php endforeach ?>
								</div>
							</div>
						</div>
						<?php if ($showSettings): ?>
							<div class="cms-pane" data-pane="settings" hidden>
								<div class="cms-pane-card">
									<?php $this->insert('editor-settings', [
										'node' => $node,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'routable' => $routable,
										'renderable' => $renderable,
										'pathsUrl' => $edit ? $links->paths($uid) : null,
									]) ?>
								</div>
							</div>
						<?php endif ?>
					</form>
				</div>
			</div>
		</div>
	</section>
	<div id="editor-preview" hidden></div>
	<script id="cosray-system-data" type="application/json"><?= json_encode(
	['panel' => $panelBase, 'system' => $system],
	$jsonFlags,
) ?></script>
</div>
