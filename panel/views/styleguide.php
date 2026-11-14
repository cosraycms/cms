<?php

use function Cosray\escape;

// Development page: strings stay untranslated on purpose, the panel
// catalogs carry what editors see.

$this->layout('layer/main');

$tokenGroups = (array) u($tokenGroups);
$fields = (array) u($fields);
$fieldset = (array) u($fieldset);
$content = (array) u($content);
$richtextFields = (array) u($richtextFields);
$richtextContent = (array) u($richtextContent);
$codeFields = (array) u($codeFields);
$codeContent = (array) u($codeContent);
$linkFields = (array) u($linkFields);
$linkContent = (array) u($linkContent);
$mediaFields = (array) u($mediaFields);
$mediaContent = (array) u($mediaContent);
$mediaAssets = (array) u($mediaAssets);
$entriesFields = (array) u($entriesFields);
$entriesContent = (array) u($entriesContent);
$blocksFields = (array) u($blocksFields);
$blocksContent = (array) u($blocksContent);
$system = u($system);
$panelBase = (string) $panelBase;
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$locales = (array) u($locales);
$defaultLocale = (string) $defaultLocale;
$fallbackField = [
	'name' => 'fallback-title',
	'label' => 'Translated title',
	'control' => ['name' => 'text', 'props' => ['placeholder' => 'Translated title']],
	'translate' => true,
];
$fallbackContent = [
	'fallback-title' => ['value' => ['en' => 'English fallback preview', 'de' => '']],
];

$fieldsByName = [];

foreach ($fields as $field) {
	if (is_array($field) && is_string($field['name'] ?? null)) {
		$fieldsByName[$field['name']] = $field;
	}
}

$fieldsetMembers = array_flip(array_filter(
	(array) ($fieldset['fields'] ?? []),
	'is_string',
));

$statuses = ['published', 'unpublished', 'changes', 'hidden', 'locked'];
$rows = (array) u($rows);

// ?section=<key> narrows the page to one section, ?theme=light|dark
// forces a theme: one URL per question, so a check needs no scrolling
// and no scripting.
$section = (string) (u($section ?? '') ?? '');
$theme = (string) (u($theme ?? '') ?? '');
$theme = in_array($theme, ['light', 'dark'], true) ? $theme : '';
?>
<?php if ($section !== ''): ?>
	<style>
		.cms-styleguide-section:not([data-section="<?= escape($section) ?>"]) {
			display: none;
		}
	</style>
<?php endif ?>
<?php if ($theme !== ''): ?>
	<script>
		document.documentElement.dataset.theme = <?= json_encode($theme, $jsonFlags) ?>;
	</script>
<?php endif ?>

<div class="cms-page cms-styleguide">
	<header class="cms-page-head">
			<h1>Styleguide</h1>
			<?php

			// Reads the effective theme, not the attribute: without one the panel
			// follows the system.
			?>
			<button
				type="button"
				class="cms-button cms-button-secondary"
				onclick="const r = document.documentElement; const dark = r.dataset.theme ? r.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches; r.dataset.theme = dark ? 'light' : 'dark'">
				Toggle theme
			</button>
	</header>

	<section class="cms-styleguide-body">
		<div class="cms-styleguide-sections">
			<section class="cms-styleguide-section" data-section="tokens">
				<h2>Tokens</h2>
				<p class="cms-styleguide-note">
					Read out of <code>panel/styles/tokens.css</code> at request time, so this list
					cannot drift from the stylesheet. Primitives are panel-internal; semantic and
					component tokens are what a project overrides in <code>@layer theme</code>.
				</p>
				<?php foreach ($tokenGroups as $group): ?>
					<details class="cms-styleguide-group"<?= $group['open'] ? ' open' : '' ?>>
						<summary><?= escape((string) $group['title']) ?> <span class="cms-styleguide-count"><?= count(
							(array) $group['tokens'],
						) ?></span></summary>
					<div class="cms-styleguide-tokens">
						<?php foreach ((array) $group['tokens'] as $token): ?>
							<?php $name = (string) $token['name']; ?>
							<div class="cms-styleguide-token">
								<?php if ($token['swatch']): ?>
									<span class="cms-styleguide-swatch" style="background: var(<?= escape($name) ?>)"></span>
								<?php elseif (str_contains($name, 'shadow')): ?>
									<span class="cms-styleguide-swatch" style="box-shadow: var(<?= escape($name) ?>)"></span>
								<?php else: ?>
									<span class="cms-styleguide-swatch is-empty"></span>
								<?php endif ?>
								<code class="cms-styleguide-name"><?= escape($name) ?></code>
								<code class="cms-styleguide-value" title="<?= escape(
									(string) $token['value'],
								) ?>"><?= escape((string) $token['value']) ?></code>
							</div>
						<?php endforeach ?>
					</div>
					</details>
				<?php endforeach ?>
			</section>

			<section class="cms-styleguide-section" data-section="buttons">
				<h2>Buttons</h2>
				<div class="cms-styleguide-sample">
					<button type="button" class="cms-button cms-button-primary"><?= \Cosray\Panel\Icon::render('floppy') ?> Save</button>
					<button type="button" class="cms-button cms-button-secondary"><?= \Cosray\Panel\Icon::render('eye') ?> Preview</button>
					<button type="button" class="cms-button cms-button-danger"><?= \Cosray\Panel\Icon::render('trash3') ?> Delete</button>
					<a class="cms-button cms-button-secondary" href="#">Link</a>
				</div>
				<div class="cms-styleguide-sample">
					<button type="button" class="cms-button cms-button-primary" disabled><?= \Cosray\Panel\Icon::render('floppy') ?> Save</button>
					<button type="button" class="cms-button cms-button-secondary" disabled><?= \Cosray\Panel\Icon::render(
						'eye',
					) ?> Preview</button>
					<button type="button" class="cms-button cms-button-danger" disabled><?= \Cosray\Panel\Icon::render('trash3') ?> Delete</button>
				</div>
				<div class="cms-styleguide-sample" data-sample="button:danger-solid">
					<button type="button" class="cms-button cms-button-secondary">Cancel</button>
					<button type="button" class="cms-button cms-button-danger cms-button-solid">Delete</button>
					<button type="button" class="cms-button cms-button-danger cms-button-solid" disabled>Delete</button>
				</div>
				<div class="cms-styleguide-sample" data-sample="button:small">
					<button type="button" class="cms-button cms-button-primary cms-button-small">Save</button>
					<button type="button" class="cms-button cms-button-secondary cms-button-small">Edit</button>
					<button type="button" class="cms-button cms-button-danger cms-button-small">Delete</button>
					<button type="button" class="cms-button cms-button-secondary cms-button-small" disabled>Use</button>
				</div>
				<div class="cms-styleguide-sample" data-sample="button:split">
					<div class="cms-split-button">
						<button type="button" class="cms-button cms-button-primary"><?= \Cosray\Panel\Icon::render('floppy') ?> Save</button>
						<button type="button" class="cms-button cms-button-primary cms-split-button-toggle" popovertarget="sample-save-options"
							aria-haspopup="menu" aria-label="More save options"><?= \Cosray\Panel\Icon::render('chevron-down') ?></button>
						<div id="sample-save-options" class="cms-action-menu" popover="auto" data-action-menu data-align="end">
							<button type="button"><?= \Cosray\Panel\Icon::render('floppy') ?> Save and publish</button>
						</div>
					</div>
					<div class="cms-split-button">
						<button type="button" class="cms-button cms-button-secondary">Export</button>
						<button type="button" class="cms-button cms-button-secondary cms-split-button-toggle" popovertarget="sample-export-options"
							aria-haspopup="menu" aria-label="More export options"><?= \Cosray\Panel\Icon::render('chevron-down') ?></button>
						<div id="sample-export-options" class="cms-action-menu" popover="auto" data-action-menu data-align="end">
							<button type="button">Export as CSV</button>
							<button type="button">Export as JSON</button>
						</div>
					</div>
				</div>
				<div class="cms-styleguide-sample" data-sample="button:menu">
					<button type="button" class="cms-button cms-button-primary" popovertarget="sample-create-options"
						aria-haspopup="menu">New entry <?= \Cosray\Panel\Icon::render('chevron-down') ?></button>
					<div id="sample-create-options" class="cms-action-menu" popover="auto" data-action-menu data-align="end">
						<a href="#">Page</a>
						<a href="#">News overview</a>
						<a href="#">Data export</a>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="dialogs">
				<h2>Dialogs and action menus</h2>
				<p class="cms-styleguide-note">The shared PHP/TypeScript shell, including menu-to-dialog focus and nested menus, and the two questions confirm() and alert() ask. These samples never submit.</p>
				<form data-meta-owner onsubmit="event.preventDefault()">
					<div class="cms-styleguide-sample">
						<?php foreach (['start', 'center', 'end'] as $align): ?>
							<button type="button" class="cms-button cms-button-secondary"
								popovertarget="sample-actions-<?= $align ?>" aria-haspopup="menu">Actions (<?= $align ?>)</button>
							<div id="sample-actions-<?= $align ?>" class="cms-action-menu" popover="auto" data-action-menu data-align="<?= $align ?>">
								<button type="button" data-dialog-open="meta"><?= \Cosray\Panel\Icon::render('gear') ?> Open dialog</button>
								<a href="#sample-icons">Icon examples</a>
								<hr />
								<button type="button" disabled>Unavailable action</button>
								<button type="button" class="cms-action-menu-danger">Destructive appearance</button>
							</div>
						<?php endforeach ?>
					</div>
					<?php $this->component('component/dialog', ['name' => 'meta', 'size' => 'compact', 'title' => 'Shared dialog']) ?>
						<div class="cms-modal-body">
							<label for="sample-dialog-name">Name</label>
							<input id="sample-dialog-name" class="cms-input" data-dialog-focus value="Live setting" />
							<div data-meta-owner>
								<button type="button" class="cms-button cms-button-secondary" popovertarget="sample-nested-actions" aria-haspopup="menu">Nested actions</button>
								<div id="sample-nested-actions" class="cms-action-menu" popover="auto" data-action-menu>
									<button type="button" data-dialog-open="meta">Open nested dialog</button>
									<button type="button" disabled>Unavailable action</button>
								</div>
								<?php $this->component('component/dialog', ['name' => 'meta', 'size' => 'compact', 'title' => 'Nested dialog']) ?>
									<div class="cms-modal-body">Escape returns to the underlying dialog.</div>
									<div class="cms-modal-footer"><button type="button" class="cms-button cms-button-secondary" data-dialog-close>Close</button></div>
								<?php $this->end() ?>
							</div>
						</div>
						<div class="cms-modal-footer"><button type="button" class="cms-button cms-button-secondary" data-dialog-close>Close</button></div>
					<?php $this->end() ?>
				</form>
				<div class="cms-styleguide-sample" data-sample="questions">
					<button type="button" class="cms-button cms-button-danger" data-dialog-open="sample-confirm">Destructive question</button>
					<button type="button" class="cms-button cms-button-secondary" data-dialog-open="sample-alert">Notice</button>
					<?php $this->component('component/dialog', ['name' => 'sample-confirm', 'size' => 'compact', 'title' => 'Delete file']) ?>
						<div class="cms-modal-body cms-confirm"><p class="cms-confirm-question">Delete the file "report.pdf"?</p></div>
						<footer class="cms-modal-footer">
							<button type="button" class="cms-button cms-button-secondary" data-dialog-close data-dialog-focus>Cancel</button>
							<button type="button" class="cms-button cms-button-danger cms-button-solid" data-dialog-close>Delete file</button>
						</footer>
					<?php $this->end() ?>
					<?php $this->component('component/dialog', ['name' => 'sample-alert', 'size' => 'compact', 'title' => 'Error']) ?>
						<div class="cms-modal-body"><p>Only one file can be added here.</p></div>
						<footer class="cms-modal-footer">
							<button type="button" class="cms-button cms-button-primary" data-dialog-close data-dialog-focus>OK</button>
						</footer>
					<?php $this->end() ?>
				</div>
			</section>

			<section class="cms-styleguide-section" id="sample-icons" data-section="icons">
				<h2>Icons</h2>
				<p class="cms-styleguide-note">Regular Bootstrap artwork shared with the element controls. Icons inherit text color and are decorative.</p>
				<div class="cms-styleguide-sample">
					<?php foreach (['plus', 'plus-circle', 'gear', 'three-dots-vertical', 'x-lg'] as $icon): ?>
						<span><?= \Cosray\Panel\Icon::render($icon) ?> <?= escape($icon) ?></span>
					<?php endforeach ?>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="status">
				<h2>Pills and status</h2>
				<div class="cms-styleguide-sample">
					<span class="cms-count">24 entries</span>
					<?php foreach ($statuses as $status): ?>
						<span class="cms-status is-<?= escape($status) ?>"><?= escape(ucfirst($status)) ?></span>
					<?php endforeach ?>
				</div>
				<?php // A status column's looks, row by row: up to the last stage that holds something, or fixed. ?>
				<?php $chains = static fn(bool $fixed): array => array_map(static function (array $stages) use ($fixed): array {
					$stages = $fixed ? $stages : \Cosray\Panel\NodeStatus::chain($stages);

					return ['stages' => $stages, 'sentence' => \Cosray\Panel\NodeStatus::sentence($stages)];
				}, (array) u($stageSamples)); ?>
				<?php foreach ([['icons', false], ['dots', false], ['icons', true], ['dots', true]] as [$look, $fixed]): ?>
					<div class="cms-styleguide-sample">
						<code><?= escape($look . ($fixed ? ' fixed' : '')) ?></code>
						<?php foreach ($chains($fixed) as $sample): ?>
							<?php $this->include('component/status-dots', ['status' => $sample, 'size' => 'sm', 'look' => $look]) ?>
						<?php endforeach ?>
					</div>
				<?php endforeach ?>
				<?php // Stage tokens as a theme would set them. ?>
				<?php $halo = '--cms-stage-halo: 1.5px; --cms-stage-halo-gap: 1.5px; --cms-stage-dot-halo: 1.5px; '
					. '--cms-stage-dot-halo-gap: 1.5px; --cms-stage-halo-strength: 45%'; ?>
				<?php $inverted = '--cms-stage-tint: 100%; --cms-stage-halo: 2px; --cms-stage-halo-gap: 1px; '
					. '--cms-stage-halo-strength: 35%; --cms-color-stage-main-ink: white; '
					. '--cms-color-stage-draft-ink: var(--cms-color-neutral-900); --cms-color-stage-mine-ink: white; '
					. '--cms-color-stage-problem-ink: white; --cms-color-stage-idle: light-dark(var(--cms-color-neutral-200), var(--cms-color-neutral-700)); '
					. '--cms-color-stage-idle-ink: light-dark(var(--cms-color-neutral-600), var(--cms-color-neutral-300)); '
					// Set on :root, offline would follow main by itself.
					. '--cms-color-stage-offline: var(--cms-color-stage-main); '
					. '--cms-color-stage-offline-ink: var(--cms-color-stage-main-ink)'; ?>
				<?php foreach ([
					['icons', 'icons with halo', $halo],
					['dots', 'dots with halo', $halo],
					['icons', 'icons inverted', $inverted],
					['dots', 'dots inverted', $inverted],
				] as [$look, $label, $style]): ?>
					<div class="cms-styleguide-sample" style="<?= escape($style) ?>">
						<code><?= escape($label) ?></code>
						<?php foreach ($chains(false) as $sample): ?>
							<?php $this->include('component/status-dots', ['status' => $sample, 'size' => 'sm', 'look' => $look]) ?>
						<?php endforeach ?>
					</div>
				<?php endforeach ?>
			</section>

			<section class="cms-styleguide-section" data-section="controls">
				<h2>Controls</h2>
				<p class="cms-styleguide-note">
					Every control in every state it can reach, in the classes the panel renders:
					editable, read-only, disabled, invalid.
				</p>
				<div class="cms-styleguide-sample" data-sample="input:states">
					<input class="cms-input" type="text" value="Editable" data-sample="input:editable" />
					<input class="cms-input" type="text" placeholder="Placeholder" data-sample="input:empty" />
					<input class="cms-input" type="text" value="Read-only" readonly data-sample="input:readonly" />
					<input class="cms-input" type="text" value="Disabled" disabled data-sample="input:disabled" />
					<input
						class="cms-input"
						type="text"
						value="Invalid"
						aria-invalid="true"
						data-sample="input:invalid" />
				</div>
				<div class="cms-styleguide-sample" data-sample="select:states">
					<select class="cms-select" data-sample="select:editable">
						<option>News</option>
						<option>Event</option>
					</select>
					<select class="cms-select" disabled data-sample="select:disabled">
						<option>News</option>
					</select>
					<label class="cms-styleguide-sample">
						<input class="cms-checkbox" type="checkbox" checked data-sample="checkbox:checked" />
						Checkbox
					</label>
					<label class="cms-styleguide-sample">
						<input class="cms-checkbox" type="checkbox" disabled data-sample="checkbox:disabled" />
						Disabled
					</label>
				</div>
				<div class="cms-styleguide-sample" data-sample="switch:states">
					<label class="cms-styleguide-sample">
						<input type="checkbox" class="cms-switch" checked data-sample="switch:on" /> Switch on
					</label>
					<label class="cms-styleguide-sample">
						<input type="checkbox" class="cms-switch" data-sample="switch:off" /> Switch off
					</label>
					<label class="cms-styleguide-sample">
						<input type="checkbox" class="cms-switch" checked disabled data-sample="switch:disabled" />
						Disabled
					</label>
				</div>
				<div class="cms-styleguide-sample" data-sample="textarea:states">
					<textarea class="cms-textarea" rows="2" data-sample="textarea:editable">Zweisprachige Betreuung für Kinder von 10 Monaten bis 3 Jahren.</textarea>
					<textarea class="cms-textarea" rows="2" readonly data-sample="textarea:readonly">Read-only</textarea>
					<textarea class="cms-textarea" rows="2" disabled data-sample="textarea:disabled">Disabled</textarea>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="fields">
				<h2>Fields</h2>
				<p class="cms-styleguide-note">
					Rendered through <code>panel/views/field/*</code> with fixture data — the same
					partials the editor uses, so these cannot fall out of step with it. The pane
					ground is the editor's, so a state is judged against the colour it sits on.
				</p>
				<?php // Mirrors the editor: the column is where its width cap lives and

				// the pane the ground it sits on, so a sample reads as it will. ?>
				<div
					class="cms-node"
					data-sample="fields:pane"
					data-content-locale-scope
					data-content-locale="<?= escape($defaultLocale) ?>"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="cms-node-pane">
						<div class="cms-node-pane-scroll">
							<div class="cms-node-column">
								<div class="cms-node-sheet">
									<?php $this->include('component/content-locales', [
										'locales' => $locales,
										'selected' => $defaultLocale,
										'controlId' => 'styleguide-fields-locale',
									]) ?>
									<?php $this->include('field/fieldset', [
										'fieldset' => $fieldset,
										'fieldsByName' => $fieldsByName,
										'content' => $content,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'uid' => 'styleguide',
										'assets' => [],
										'pathSourceFields' => [],
									]) ?>
									<div class="cms-fields">
										<?php foreach ($fields as $field): ?>
											<?php if (isset($fieldsetMembers[$field['name'] ?? ''])) {
												continue;
											} ?>
											<?php $this->include('field/item', [
												'field' => $field,
												'content' => $content,
												'locales' => $locales,
												'defaultLocale' => $defaultLocale,
												'uid' => 'styleguide',
												'assets' => [],
												'pathSourceFields' => [],
											]) ?>
										<?php endforeach ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<h3>Fallback preview</h3>
				<p class="cms-styleguide-note">
					The German target is empty. The English value is a placeholder only and
					disappears while the input has focus.
				</p>
				<div
					class="cms-node"
					data-content-locale-scope
					data-content-locale="de"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="cms-node-column">
						<div class="cms-node-sheet">
							<?php $this->include('component/content-locales', [
								'locales' => $locales,
								'selected' => 'de',
								'controlId' => 'styleguide-fallback-locale',
							]) ?>
							<div class="cms-fields">
								<?php $this->include('field/item', [
									'field' => $fallbackField,
									'content' => $fallbackContent,
									'locales' => $locales,
									'defaultLocale' => 'de',
									'uid' => 'styleguide',
									'assets' => [],
									'pathSourceFields' => [],
								]) ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="alignment">
				<h2>Field alignment</h2>
				<p class="cms-styleguide-note">
					Mixed label lengths share a control start line. Help and errors stay with their
					own control, while row spans and conditional fields keep their grid placement.
					Resize the viewport to check wrapping and stacked fields.
				</p>
				<?php $this->include('styleguide/alignment') ?>
			</section>

			<section class="cms-styleguide-section" data-section="richtext">
				<h2>Richtext</h2>
				<p class="cms-styleguide-note">
					The default toolbar, a <code>#[Tools]</code>-trimmed field with the source view,
					and the read-only state, which mounts without a toolbar.
				</p>
				<div
					class="cms-node"
					data-content-locale-scope
					data-content-locale="<?= escape($defaultLocale) ?>"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="cms-node-column">
						<div class="cms-node-sheet">
							<?php $this->include('component/content-locales', [
								'locales' => $locales,
								'selected' => $defaultLocale,
								'controlId' => 'styleguide-richtext-locale',
							]) ?>
							<div class="cms-fields">
								<?php foreach ($richtextFields as $field): ?>
									<?php $this->include('field/item', [
										'field' => $field,
										'content' => $richtextContent,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'uid' => 'styleguide',
										'assets' => [],
										'pathSourceFields' => [],
									]) ?>
								<?php endforeach ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="code">
				<h2>Code</h2>
				<p class="cms-styleguide-note">
					A field offering several syntaxes, a translated field whose empty German value
					shows the English fallback, and the read-only state.
				</p>
				<div
					class="cms-node"
					data-content-locale-scope
					data-content-locale="<?= escape($defaultLocale) ?>"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="cms-node-column">
						<div class="cms-node-sheet">
							<?php $this->include('component/content-locales', [
								'locales' => $locales,
								'selected' => $defaultLocale,
								'controlId' => 'styleguide-code-locale',
							]) ?>
							<div class="cms-fields">
								<?php foreach ($codeFields as $field): ?>
									<?php $this->include('field/item', [
										'field' => $field,
										'content' => $codeContent,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'uid' => 'styleguide',
										'assets' => [],
										'pathSourceFields' => [],
									]) ?>
								<?php endforeach ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="links">
				<h2>Links</h2>
				<p class="cms-styleguide-note">
					Link fields: empty and required, a file opening in a new tab, a translated link whose
					empty German value shows the English one, and the read-only state.
				</p>
				<div
					class="cms-node"
					data-content-locale-scope
					data-content-locale="<?= escape($defaultLocale) ?>"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="cms-node-column">
						<div class="cms-node-sheet">
							<?php $this->include('component/content-locales', [
								'locales' => $locales,
								'selected' => $defaultLocale,
								'controlId' => 'styleguide-link-locale',
							]) ?>
							<div class="cms-fields">
								<?php foreach ($linkFields as $field): ?>
									<?php $this->include('field/item', [
										'field' => $field,
										'content' => $linkContent,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'uid' => 'styleguide',
										'assets' => $mediaAssets,
										'pathSourceFields' => [],
									]) ?>
								<?php endforeach ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="media">
				<h2>Media</h2>
				<p class="cms-styleguide-note">
					The media controls — a single image, a gallery, files, a video — filled, empty
					and read-only. Fixture assets are inline SVG plates; uploads and the library
					picker are live. The toggle drags a pretend file over every control.
				</p>
				<?php // A synthetic drag: the controls answer dragenter and dragleave alone. ?>
				<button
					type="button"
					class="cms-button cms-button-secondary cms-button-small"
					aria-pressed="false"
					onclick="const on = this.getAttribute('aria-pressed') !== 'true'; this.setAttribute('aria-pressed', String(on)); const transfer = new DataTransfer(); transfer.items.add(new File(['x'], 'drop.png', { type: 'image/png' })); for (const root of this.closest('[data-section]').querySelectorAll('.cms-dropzone')) { root.dispatchEvent(new DragEvent(on ? 'dragenter' : 'dragleave', { bubbles: true, cancelable: true, dataTransfer: transfer })); }">
					Show drop state
				</button>
				<div
					class="cms-node"
					data-content-locale-scope
					data-content-locale="<?= escape($defaultLocale) ?>"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="cms-node-column">
						<div class="cms-node-sheet">
							<?php $this->include('component/content-locales', [
								'locales' => $locales,
								'selected' => $defaultLocale,
								'controlId' => 'styleguide-media-locale',
							]) ?>
							<div class="cms-fields">
								<?php foreach ($mediaFields as $field): ?>
									<?php $this->include('field/item', [
										'field' => $field,
										'content' => $mediaContent,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'uid' => 'styleguide',
										'assets' => $mediaAssets,
										'pathSourceFields' => [],
									]) ?>
								<?php endforeach ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="entries">
				<h2>Entries</h2>
				<p class="cms-styleguide-note">
					Stored rows collapse to a summary and open their form beneath it. The read-only
					field keeps its rows and drops everything that restructures them.
				</p>
				<div
					class="cms-node"
					data-content-locale-scope
					data-content-locale="<?= escape($defaultLocale) ?>"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="cms-node-column">
						<div class="cms-node-sheet">
							<?php $this->include('component/content-locales', [
								'locales' => $locales,
								'selected' => $defaultLocale,
								'controlId' => 'styleguide-entries-locale',
							]) ?>
							<div class="cms-fields">
								<?php foreach ($entriesFields as $field): ?>
									<?php $this->include('field/item', [
										'field' => $field,
										'content' => $entriesContent,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'uid' => 'styleguide',
										'assets' => $mediaAssets,
										'pathSourceFields' => [],
									]) ?>
								<?php endforeach ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="blocks">
				<h2>Blocks</h2>
				<p class="cms-styleguide-note">
					A one-column list and a twelve-column grid, both on the field's own well. A
					block's chrome appears on the active row; the + inserts before it, the footer
					appends, and More blocks opens the catalog.
				</p>
				<div
					class="cms-node"
					data-content-locale-scope
					data-content-locale="<?= escape($defaultLocale) ?>"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="cms-node-column">
						<div class="cms-node-sheet">
							<?php $this->include('component/content-locales', [
								'locales' => $locales,
								'selected' => $defaultLocale,
								'controlId' => 'styleguide-blocks-locale',
							]) ?>
							<div class="cms-fields">
								<?php foreach ($blocksFields as $field): ?>
									<?php $this->include('field/item', [
										'field' => $field,
										'content' => $blocksContent,
										'locales' => $locales,
										'defaultLocale' => $defaultLocale,
										'uid' => 'styleguide',
										'assets' => $mediaAssets,
										'pathSourceFields' => [],
									]) ?>
								<?php endforeach ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="inspector">
				<h2>Inspector</h2>
				<p class="cms-styleguide-note">
					Rendered through <code>panel/views/node/inspector.php</code> — the status,
					paths and advanced tabs of an existing node.
				</p>
				<?php $this->include('node/inspector', (array) u($inspector)) ?>
			</section>

			<section class="cms-styleguide-section" data-section="page-head">
				<h2>Page head</h2>
				<p class="cms-styleguide-note">
					Every part is optional and the order is fixed. <code>cms-page</code>
					styles all of them; a screen adds only what is its own. The toolbar is a row of
					the content column, outside its scroller, so it stays put while the content
					moves.
				</p>
				<div class="cms-page cms-styleguide-sample-page">
					<header class="cms-page-head">
						<div class="cms-page-titles">
							<div class="cms-page-line">
								<nav class="cms-page-breadcrumb" aria-label="Breadcrumb">
									<a href="#">Pages</a>
									<span class="cms-page-sep" aria-hidden="true">/</span>
								</nav>
								<h1>A page with a rather long title</h1>
								<span class="cms-count">42 entries</span>
								<span class="cms-status is-published">Published</span>
							</div>
						</div>
						<div class="cms-page-actions">
							<button type="button" class="cms-button cms-button-secondary">Preview</button>
							<button type="button" class="cms-button cms-button-primary">Save</button>
						</div>
					</header>
					<div class="cms-page-toolbar">
						<input class="cms-input" type="search" aria-label="Search" placeholder="Search entries …" />
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="listing">
				<h2>Listing</h2>
				<p class="cms-styleguide-note">
					Rendered through <code>panel/views/collection/row.php</code>: tree depth, hover
					actions and every status badge at once, which a real collection rarely shows.
				</p>
				<div class="cms-collection">
					<div class="cms-collection-listing">
						<div class="cms-collection-scroll">
							<table
								class="cms-list"
								role="table"
								style="--columns: var(--cms-list-select-width) minmax(12rem, 2fr) max-content minmax(5rem, auto) minmax(5rem, auto) max-content">
								<thead role="rowgroup">
									<tr role="row">
										<th class="cms-list-col-select" role="columnheader">
											<input type="checkbox" data-bulk-all aria-label="Select all" />
										</th>
										<th class="is-lead" role="columnheader"><span class="cms-list-heading">Title</span></th>
										<th class="cms-list-col-status" role="columnheader">Status</th>
										<th role="columnheader"><span class="cms-list-heading">Type</span></th>
										<th role="columnheader"><span class="cms-list-heading">Modified</span></th>
										<th class="cms-list-col-actions" role="columnheader"></th>
									</tr>
								</thead>
								<tbody role="rowgroup">
									<?php foreach ($rows as $row): ?>
										<?php $this->include('collection/row', [
											'row' => $row,
											'treeMode' => true,
											'showChildren' => true,
											'hasRowActions' => true,
											'bulk' => true,
										]) ?>
									<?php endforeach ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</section>

			<section class="cms-styleguide-section" data-section="empty">
				<h2>Empty state</h2>
				<div class="cms-collection">
					<div class="cms-collection-listing">
						<div class="cms-collection-empty">
							<div class="cms-collection-empty-icon" aria-hidden="true">⌁</div>
							<strong>No entries yet</strong>
							<p>Create the first entry to get started.</p>
						</div>
					</div>
				</div>
			</section>
		</div>
	</section>
	<?php // Installs the editor bridge the media samples upload through. ?>
	<script id="cosray-system-data" type="application/json"><?= json_encode(
		['panel' => $panelBase, 'system' => $system],
		$jsonFlags,
	) ?></script>
</div>
