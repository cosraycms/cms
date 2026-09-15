<?php

use function Cosray\escape;

// The settings rail beside the field pane, in tabs: the node's status, its
// route paths, and an advanced tab with the handle and the facts. Every
// control sits inside the editor form and submits with the content fields
// through the same merge patch, the hidden tabs included. The markup arrives
// with the first tab selected so nothing flashes before the tabs behavior runs.

$node = (array) $this->unwrap($node);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$routable = (bool) $routable;
$renderable = (bool) $renderable;
$contentLocales = (bool) ($this->unwrap($contentLocales ?? null) ?? false);
$pathsUrl = $this->unwrap($pathsUrl);
$generatedPaths = (array) $this->unwrap($generatedPaths ?? []);
$meta = (array) ($this->unwrap($meta ?? null) ?? []);
$paths = is_array($node['paths'] ?? null) ? $node['paths'] : [];
$handle = $node['handle'] ?? null;

$typeLabel = $node['type']['label'] ?? null;
$created = $meta['created'] ?? null;
$editorName = $meta['editor'] ?? null;
$showType = is_string($typeLabel) && $typeLabel !== '';
$showCreated = is_string($created) && $created !== '';
$showEditor = is_string($editorName) && $editorName !== '';
$showMeta = $showType || $renderable || $showCreated || $showEditor;

$tabs = [];

if ($contentLocales || $renderable) {
	$tabs['status'] = __('editor:tab-status');
}

if ($routable) {
	$tabs['paths'] = __('editor:tab-paths');
}

$tabs['advanced'] = __('editor:tab-advanced');
$first = array_key_first($tabs);
?>
<aside class="cms-inspector" aria-label="<?= escape(__('editor:settings')) ?>" data-tabs>
	<div class="cms-tabs" role="tablist" aria-label="<?= escape(__('editor:settings')) ?>">
		<?php foreach ($tabs as $key => $label): ?>
			<button
				type="button"
				class="tab"
				role="tab"
				id="cms-inspector-tab-<?= $key ?>"
				aria-controls="cms-inspector-panel-<?= $key ?>"
				aria-selected="<?= $key === $first ? 'true' : 'false' ?>"
				tabindex="<?= $key === $first ? '0' : '-1' ?>">
				<?= escape($label) ?>
			</button>
		<?php endforeach ?>
	</div>
	<div class="scroll">
		<?php if (isset($tabs['status'])): ?>
			<div
				id="cms-inspector-panel-status"
				role="tabpanel"
				aria-labelledby="cms-inspector-tab-status"
				<?= $first === 'status' ? '' : 'hidden' ?>>
				<?php if ($contentLocales): ?>
					<section class="section">
						<?php $this->insert('component/content-locales', [
							'locales' => $locales,
							'selected' => $defaultLocale,
						]) ?>
					</section>
				<?php endif ?>

				<?php if ($renderable): ?>
					<section class="section">
						<label class="toggle">
							<span class="copy">
								<span class="title"><?= escape(__('editor:published-label')) ?></span>
								<span class="help"><?= escape(__('editor:published-help')) ?></span>
							</span>
							<input type="hidden" name="published" value="" />
							<input
								id="editor-published-switch"
								type="checkbox"
								class="cms-switch"
								name="published"
								value="1"
								<?= $node['published'] ?? false ? 'checked' : '' ?> />
						</label>
						<?php $this->insert('node/changes-note', ['draft' => $meta['draft'] ?? null]) ?>

						<label class="toggle">
							<span class="copy">
								<span class="title"><?= escape(__('editor:hidden-label')) ?></span>
								<span class="help"><?= escape(__('editor:hidden-help')) ?></span>
							</span>
							<input type="hidden" name="hidden" value="" />
							<input
								type="checkbox"
								class="cms-switch"
								name="hidden"
								value="1"
								<?= $node['hidden'] ?? false ? 'checked' : '' ?> />
						</label>
					</section>
				<?php endif ?>
			</div>
		<?php endif ?>

		<?php if ($routable): ?>
			<div
				id="cms-inspector-panel-paths"
				role="tabpanel"
				aria-labelledby="cms-inspector-tab-paths"
				<?= $first === 'paths' ? '' : 'hidden' ?>>
				<section
					class="section"
					data-paths
					data-locales="<?= escape(json_encode(array_values($locales), JSON_THROW_ON_ERROR)) ?>"
					data-note-fallback="<?= escape(__('editor:path-fallback', ['language' => '{language}'])) ?>"
					data-note-generated="<?= escape(__('editor:path-generated')) ?>"
					data-note-none="<?= escape(__('editor:path-none')) ?>">
					<div class="bar">
						<h2 class="heading"><?= escape(__('editor:paths')) ?></h2>
						<button type="button" class="cms-button secondary small" data-paths-open>
							<?= escape(__('editor:paths-edit')) ?>
						</button>
					</div>
					<dl class="paths">
						<?php foreach ($locales as $locale): ?>
							<?php $path = (string) ($paths[$locale['id']] ?? ''); ?>
							<div class="path" data-path-row="<?= escape($locale['id']) ?>">
								<dt><?= escape($locale['title']) ?></dt>
								<dd>
									<span class="url" data-path-value<?= $path === '' ? ' hidden' : '' ?>><?= escape($path) ?></span>
									<span class="hint" data-path-note hidden></span>
								</dd>
							</div>
						<?php endforeach ?>
					</dl>

					<dialog class="cms-modal" data-paths-dialog>
						<?php $this->insert('component/modal-header', ['title' => __('editor:paths')]) ?>
						<div class="modal-body cms-settings">
							<?php foreach ($locales as $locale): ?>
								<div class="field">
									<label class="label" for="cms-node-path-<?= escape($locale['id']) ?>">
										<?= escape($locale['title']) ?>
									</label>
									<input
										id="cms-node-path-<?= escape($locale['id']) ?>"
										class="cms-input js-path-source"
										type="text"
										name="paths[<?= escape($locale['id']) ?>]"
										data-path-locale="<?= escape($locale['id']) ?>"
										value="<?= escape((string) ($paths[$locale['id']] ?? '')) ?>" />
									<div class="suggestion" data-path-suggestion hidden>
										<span class="text">
											<?= escape(__('editor:path-suggestion')) ?>
											<span data-path-suggestion-value></span>
										</span>
										<button type="button" class="cms-button secondary small" data-path-use>
											<?= escape(__('editor:path-use')) ?>
										</button>
									</div>
								</div>
							<?php endforeach ?>
						</div>
						<footer class="modal-footer">
							<button type="button" class="cms-button primary" data-dialog-close>
								<?= escape(__('editor:paths-done')) ?>
							</button>
						</footer>
					</dialog>

					<?php if (is_string($pathsUrl)): ?>
						<?php $this->insert('editor-paths', [
							'paths' => $generatedPaths,
							'pathsUrl' => $pathsUrl,
						]) ?>
					<?php endif ?>
				</section>
			</div>
		<?php endif ?>

		<div
			id="cms-inspector-panel-advanced"
			role="tabpanel"
			aria-labelledby="cms-inspector-tab-advanced"
			<?= $first === 'advanced' ? '' : 'hidden' ?>>
			<section class="section">
				<div class="field">
					<label class="label" for="cms-node-handle"><?= escape(__('editor:handle')) ?></label>
					<input
						id="cms-node-handle"
						class="cms-input js-path-source"
						type="text"
						name="handle"
						maxlength="64"
						pattern="(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?"
						value="<?= escape(is_string($handle) ? $handle : '') ?>" />
				</div>
			</section>

			<?php if ($showMeta): ?>
				<section class="section">
					<dl class="facts">
						<?php if ($showType): ?>
							<dt><?= escape(__('editor:type')) ?></dt>
							<dd><?= escape($typeLabel) ?></dd>
						<?php endif ?>
						<?php if ($renderable): ?>
							<dt><?= escape(__('editor:internal-id')) ?></dt>
							<dd><code><?= escape((string) ($node['uid'] ?? '')) ?></code></dd>
						<?php endif ?>
						<?php if ($showCreated): ?>
							<dt><?= escape(__('editor:created')) ?></dt>
							<dd><?= escape($created) ?></dd>
						<?php endif ?>
						<?php if ($showEditor): ?>
							<dt><?= escape(__('editor:edited-by')) ?></dt>
							<dd><?= escape($editorName) ?></dd>
						<?php endif ?>
					</dl>
				</section>
			<?php endif ?>
		</div>
	</div>
</aside>
