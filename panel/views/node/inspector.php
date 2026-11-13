<?php

use Cosray\Panel\Icon;

use function Cosray\escape;

// The settings rail beside the field pane, in tabs: the node's status, its
// route paths, and an advanced tab with the handle and the facts. Every
// control sits inside the editor form and submits with the content fields
// through the same merge patch, the hidden tabs included. The markup arrives
// with the first tab selected so nothing flashes before the tabs behavior runs.
// Collapsed, a strip of quick controls replaces the drawer. Its publication
// switch is an unnamed copy that flips the drawer's switch.

$node = (array) u($node);
$locales = (array) u($locales);
$defaultLocale = (string) $defaultLocale;
$routable = (bool) $routable;
$renderable = (bool) $renderable;
// The status view of a stored node outside an embed; null otherwise.
$status = u($status ?? null);
$status = is_array($status) ? $status : null;
$visibility = is_array($status['visibility'] ?? null) ? $status['visibility'] : null;
$contentLocales = (bool) (u($contentLocales ?? null) ?? false);
$collapsed = (bool) $collapsed;
$pathsUrl = u($pathsUrl);
$generatedPaths = (array) u($generatedPaths ?? []);
$meta = (array) (u($meta ?? null) ?? []);
$paths = is_array($node['paths'] ?? null) ? $node['paths'] : [];
$handle = $node['handle'] ?? null;
$changed = (array) u($changed ?? []);

$typeLabel = $node['type']['label'] ?? null;
$created = $meta['created'] ?? null;
$editorName = $meta['editor'] ?? null;
$showType = is_string($typeLabel) && $typeLabel !== '';
$showCreated = is_string($created) && $created !== '';
$showEditor = is_string($editorName) && $editorName !== '';
$showMeta = $showType || $renderable || $showCreated || $showEditor;

$tabs = [];

if ($contentLocales || $status !== null) {
	$tabs['status'] = __('editor:tab-status');
}

if ($routable) {
	$tabs['paths'] = __('editor:tab-paths');
}

$tabs['advanced'] = __('editor:tab-advanced');
$first = array_key_first($tabs);
$icons = ['status' => 'toggles', 'paths' => 'link-45deg', 'advanced' => 'sliders'];
$show = __('editor:settings-show');
$hide = __('editor:settings-hide');
$publishedLabel = __('editor:published-label');
// Settings an editor restored from an autosave changes, by the tab they are in.
$changedIn = [
	'status' => false,
	'paths' => in_array('paths', $changed, true),
	'advanced' => in_array('handle', $changed, true),
];
$mark = static fn(string $setting): string => in_array($setting, $changed, true)
	? '<span class="cms-changed" data-restored-mark>' . escape(__('editor:changed')) . '</span>'
	: '';
?>
<aside
	class="cms-inspector"
	aria-label="<?= escape(__('editor:settings')) ?>"
	data-tabs
	data-inspector
	<?= $collapsed ? 'data-collapsed' : '' ?>>
	<div class="cms-inspector-strip cms-scrollbar-quiet">
		<button
			type="button"
			class="cms-inspector-tool"
			title="<?= escape($show) ?>"
			aria-label="<?= escape($show) ?>"
			data-inspector-expand>
			<?= Icon::render('layout-sidebar-inset-reverse') ?>
		</button>
		<?php if ($contentLocales): ?>
			<?php $this->include('component/content-locales', [
				'locales' => $locales,
				'selected' => $defaultLocale,
				'controlId' => 'cms-content-locale-strip',
				'strip' => true,
			]) ?>
		<?php endif ?>
		<?php if ($visibility !== null): ?>
			<input
				type="checkbox"
				class="cms-switch"
				title="<?= escape($publishedLabel) ?>"
				aria-label="<?= escape($publishedLabel) ?>"
				data-inspector-published
				data-editor-state
				<?= $visibility['published'] ? 'checked' : '' ?> />
		<?php endif ?>
		<div class="cms-inspector-shortcuts">
			<?php foreach ($tabs as $key => $label): ?>
				<button
					type="button"
					class="cms-inspector-tool"
					title="<?= escape($label) ?>"
					aria-label="<?= escape($label) ?>"
					data-inspector-open="cms-inspector-tab-<?= $key ?>">
					<?= Icon::render($icons[$key]) ?>
				</button>
			<?php endforeach ?>
		</div>
	</div>
	<div class="cms-inspector-drawer">
		<div class="cms-inspector-top">
			<div class="cms-tabs" role="tablist" aria-label="<?= escape(__('editor:settings')) ?>">
				<?php foreach ($tabs as $key => $label): ?>
					<button
						type="button"
						class="cms-tabs-tab"
						role="tab"
						id="cms-inspector-tab-<?= $key ?>"
						aria-controls="cms-inspector-panel-<?= $key ?>"
						aria-selected="<?= $key === $first ? 'true' : 'false' ?>"
						tabindex="<?= $key === $first ? '0' : '-1' ?>">
						<?= escape($label) ?>
						<?php if ($changedIn[$key]): ?>
							<span
								class="cms-changed-dot"
								data-restored-mark
								title="<?= escape(__('editor:changed')) ?>"></span>
						<?php endif ?>
					</button>
				<?php endforeach ?>
			</div>
			<button
				type="button"
				class="cms-inspector-tool"
				title="<?= escape($hide) ?>"
				aria-label="<?= escape($hide) ?>"
				data-inspector-collapse>
				<?= Icon::render('layout-sidebar-inset-reverse') ?>
			</button>
		</div>
		<div class="cms-inspector-scroll cms-scrollbar-quiet">
			<?php if (isset($tabs['status'])): ?>
				<div
					id="cms-inspector-panel-status"
					role="tabpanel"
					aria-labelledby="cms-inspector-tab-status"
					<?= $first === 'status' ? '' : 'hidden' ?>>
					<?php if ($contentLocales): ?>
						<section class="cms-inspector-section">
							<?php $this->include('component/content-locales', [
								'locales' => $locales,
								'selected' => $defaultLocale,
							]) ?>
						</section>
					<?php endif ?>

					<?php if ($status !== null): ?>
						<section class="cms-inspector-section">
							<?php $this->include('node/status', ['status' => $status]) ?>
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
						class="cms-inspector-section"
						data-paths
						data-locales="<?= escape(json_encode(array_values($locales), JSON_THROW_ON_ERROR)) ?>"
						data-note-fallback="<?= escape(__('editor:path-fallback', ['language' => '{language}'])) ?>"
						data-note-generated="<?= escape(__('editor:path-generated')) ?>"
						data-note-none="<?= escape(__('editor:path-none')) ?>">
						<div class="cms-inspector-bar">
							<h2 class="cms-inspector-heading"><?= escape(__('editor:paths')) ?><?= $mark('paths') ?></h2>
							<button type="button" class="cms-button cms-button-secondary cms-button-small" data-dialog-open="paths">
								<?= escape(__('editor:paths-edit')) ?>
							</button>
						</div>
						<dl class="cms-inspector-paths">
							<?php foreach ($locales as $locale): ?>
								<?php $path = (string) ($paths[$locale['id']] ?? ''); ?>
								<div class="cms-inspector-path" data-path-row="<?= escape($locale['id']) ?>">
									<dt><?= escape($locale['title']) ?></dt>
									<dd>
										<span class="cms-inspector-url" data-path-value<?= $path === '' ? ' hidden' : '' ?>><?= escape($path) ?></span>
										<span class="cms-inspector-hint" data-path-note hidden></span>
									</dd>
								</div>
							<?php endforeach ?>
						</dl>

						<?php $this->component('component/dialog', ['name' => 'paths', 'title' => __('editor:paths')]) ?>
							<div class="cms-modal-body cms-settings">
								<?php foreach ($locales as $locale): ?>
									<div class="cms-inspector-field cms-settings-field">
										<label class="cms-inspector-label" for="cms-node-path-<?= escape($locale['id']) ?>">
											<?= escape($locale['title']) ?>
										</label>
										<input
											id="cms-node-path-<?= escape($locale['id']) ?>"
											class="cms-input cms-path-source"
											type="text"
											name="paths[<?= escape($locale['id']) ?>]"
											data-path-locale="<?= escape($locale['id']) ?>"
											value="<?= escape((string) ($paths[$locale['id']] ?? '')) ?>" />
										<div class="cms-inspector-suggestion" data-path-suggestion hidden>
											<span class="cms-inspector-text">
												<?= escape(__('editor:path-suggestion')) ?>
												<span data-path-suggestion-value></span>
											</span>
											<button type="button" class="cms-button cms-button-secondary cms-button-small" data-path-use>
												<?= escape(__('editor:path-use')) ?>
											</button>
										</div>
									</div>
								<?php endforeach ?>
							</div>
							<footer class="cms-modal-footer">
								<button type="button" class="cms-button cms-button-primary" data-dialog-close>
									<?= escape(__('editor:paths-done')) ?>
								</button>
							</footer>
						<?php $this->end() ?>

						<?php if (is_string($pathsUrl)): ?>
							<?php $this->include('editor-paths', [
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
				<section class="cms-inspector-section">
					<div class="cms-inspector-field">
						<label class="cms-inspector-label" for="cms-node-handle"><?= escape(__('editor:handle')) ?><?= $mark('handle') ?></label>
						<input
							id="cms-node-handle"
							class="cms-input cms-path-source"
							type="text"
							name="handle"
							maxlength="64"
							pattern="(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?"
							value="<?= escape(is_string($handle) ? $handle : '') ?>" />
					</div>
				</section>

				<?php if ($showMeta): ?>
					<section class="cms-inspector-section">
						<dl class="cms-inspector-facts">
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
	</div>
</aside>
