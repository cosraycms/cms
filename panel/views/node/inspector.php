<?php

use function Cosray\escape;

// The settings rail beside the field pane, replacing the settings tab. Handle,
// route paths and the published/hidden flags sit inside the editor form and
// submit with the content fields through the same merge patch.

$node = (array) $this->unwrap($node);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$routable = (bool) $routable;
$renderable = (bool) $renderable;
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
?>
<aside class="cms-inspector" aria-label="<?= escape(__('editor:settings')) ?>">
	<div class="head">
		<span class="title"><?= escape(__('editor:settings')) ?></span>
	</div>
	<div class="scroll">
		<?php if ($renderable): ?>
			<section class="section">
				<label class="toggle">
					<span class="copy">
						<span class="title"><?= escape(__('editor:published-label')) ?></span>
						<span class="help"><?= escape(__('editor:published-help')) ?></span>
					</span>
					<input type="hidden" name="published" value="" />
					<input
						type="checkbox"
						class="cms-switch"
						name="published"
						value="1"
						<?= $node['published'] ?? false ? 'checked' : '' ?> />
				</label>

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

		<?php if ($routable): ?>
			<section class="section">
				<h2 class="heading"><?= escape(__('editor:paths')) ?></h2>
				<?php // Long values in a narrow rail: the inputs take the full width

				// and the generated suggestions sit under them rather than beside. ?>
				<?php foreach ($locales as $locale): ?>
					<div class="field">
						<label class="label" for="cms-node-path-<?= escape($locale['id']) ?>">
							<?= escape($locale['title']) ?>
						</label>
						<input
							id="cms-node-path-<?= escape($locale['id']) ?>"
							class="js-path-source"
							type="text"
							name="paths[<?= escape($locale['id']) ?>]"
							value="<?= escape((string) ($paths[$locale['id']] ?? '')) ?>" />
					</div>
				<?php endforeach ?>

				<?php if (is_string($pathsUrl)): ?>
					<?php $this->insert('editor-paths', [
						'paths' => $generatedPaths,
						'submitted' => $paths,
						'pathsUrl' => $pathsUrl,
					]) ?>
				<?php endif ?>
			</section>
		<?php endif ?>

		<section class="section">
			<div class="field">
				<label class="label" for="cms-node-handle"><?= escape(__('editor:handle')) ?></label>
				<input
					id="cms-node-handle"
					class="js-path-source"
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
</aside>
