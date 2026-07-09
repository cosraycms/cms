<?php

use function Cosray\escape;

// Settings pane inside the node form: handle, route paths and the
// published/hidden flags submit with the content fields through the
// same merge patch.

$node = (array) $this->unwrap($node);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;
$routable = (bool) $routable;
$renderable = (bool) $renderable;
$pathsUrl = $this->unwrap($pathsUrl);
$generatedPaths = (array) $this->unwrap($generatedPaths ?? []);
$paths = is_array($node['paths'] ?? null) ? $node['paths'] : [];
$handle = $node['handle'] ?? null;
?>
<div class="cms-settings">
	<div class="cms-settings-row">
		<label class="cms-field-label" for="cms-node-handle">
			<?= escape(__('editor:handle')) ?>:
		</label>
		<div class="cms-settings-handle-value">
			<input
				id="cms-node-handle"
				class="js-path-source"
				type="text"
				name="handle"
				maxlength="64"
				pattern="(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?"
				value="<?= escape(is_string($handle) ? $handle : '') ?>" />
		</div>
	</div>
	<?php if ($routable): ?>
		<div class="cms-settings-paths">
			<?php foreach ($locales as $locale): ?>
				<div class="cms-settings-path">
					<div class="cms-field-label"><?= escape($locale['title']) ?>:</div>
					<div class="cms-settings-path-value">
						<input
							class="js-path-source"
							type="text"
							name="paths[<?= escape($locale['id']) ?>]"
							value="<?= escape((string) ($paths[$locale['id']] ?? '')) ?>" />
					</div>
				</div>
			<?php endforeach ?>
		</div>
		<?php if (is_string($pathsUrl)): ?>
			<?php $this->insert('editor-paths', [
				'paths' => $generatedPaths,
				'submitted' => $paths,
				'pathsUrl' => $pathsUrl,
			]) ?>
		<?php endif ?>
	<?php endif ?>
	<?php if ($renderable): ?>
		<div class="cms-settings-renderable">
			<div class="cms-settings-section">
				<label class="cms-toggle-line">
					<span class="cms-toggle-line-copy">
						<span class="cms-toggle-line-title"><?= escape(__('editor:published-label')) ?></span>
						<span class="cms-toggle-line-subtitle">
							<?= escape(__('editor:published-help')) ?>
						</span>
					</span>
					<input type="hidden" name="published" value="" />
					<input
						type="checkbox"
						class="cms-switch"
						name="published"
						value="1"
						<?= $node['published'] ?? false ? 'checked' : '' ?> />
				</label>
			</div>
			<div class="cms-settings-section">
				<label class="cms-toggle-line">
					<span class="cms-toggle-line-copy">
						<span class="cms-toggle-line-title"><?= escape(__('editor:hidden-label')) ?></span>
						<span class="cms-toggle-line-subtitle">
							<?= escape(__('editor:hidden-help')) ?>
						</span>
					</span>
					<input type="hidden" name="hidden" value="" />
					<input
						type="checkbox"
						class="cms-switch"
						name="hidden"
						value="1"
						<?= $node['hidden'] ?? false ? 'checked' : '' ?> />
				</label>
			</div>
			<div class="cms-settings-row">
				<div class="cms-field-label"><?= escape(__('editor:internal-id')) ?>:</div>
				<div class="cms-settings-value"><?= escape((string) ($node['uid'] ?? '')) ?></div>
			</div>
		</div>
	<?php endif ?>
</div>
