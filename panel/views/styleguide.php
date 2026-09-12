<?php

use function Cosray\escape;

// Development page: strings stay untranslated on purpose, the panel
// catalogs carry what editors see.

$this->layout('layer/main');

$tokenGroups = (array) $this->unwrap($tokenGroups);
$fields = (array) $this->unwrap($fields);
$fieldset = (array) $this->unwrap($fieldset);
$content = (array) $this->unwrap($content);
$richtextFields = (array) $this->unwrap($richtextFields);
$richtextContent = (array) $this->unwrap($richtextContent);
$mediaFields = (array) $this->unwrap($mediaFields);
$mediaContent = (array) $this->unwrap($mediaContent);
$mediaAssets = (array) $this->unwrap($mediaAssets);
$entriesFields = (array) $this->unwrap($entriesFields);
$entriesContent = (array) $this->unwrap($entriesContent);
$blocksFields = (array) $this->unwrap($blocksFields);
$blocksContent = (array) $this->unwrap($blocksContent);
$system = $this->unwrap($system);
$panelBase = (string) $panelBase;
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$locales = (array) $this->unwrap($locales);
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

$statuses = ['published', 'draft', 'hidden', 'locked'];
$rows = (array) $this->unwrap($rows);

?>

<div class="page cms-styleguide">
	<header class="head">
			<h1>Styleguide</h1>
			<?php

			// Reads the effective theme, not the attribute: without one the panel
			// follows the system.
			?>
			<button
				type="button"
				class="cms-button secondary"
				onclick="const r = document.documentElement; const dark = r.dataset.theme ? r.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches; r.dataset.theme = dark ? 'light' : 'dark'">
				Toggle theme
			</button>
	</header>

	<section class="body">
		<div class="sections">
			<section class="section">
				<h2>Tokens</h2>
				<p class="note">
					Read out of <code>panel/styles/tokens.css</code> at request time, so this list
					cannot drift from the stylesheet. Primitives are panel-internal; semantic and
					component tokens are what a project overrides in <code>@layer theme</code>.
				</p>
				<?php foreach ($tokenGroups as $group): ?>
					<details class="group"<?= $group['open'] ? ' open' : '' ?>>
						<summary><?= escape((string) $group['title']) ?> <span class="count"><?= count(
							(array) $group['tokens'],
						) ?></span></summary>
					<div class="tokens">
						<?php foreach ((array) $group['tokens'] as $token): ?>
							<?php $name = (string) $token['name']; ?>
							<div class="token">
								<?php if ($token['swatch']): ?>
									<span class="swatch" style="background: var(<?= escape($name) ?>)"></span>
								<?php elseif (str_contains($name, 'shadow')): ?>
									<span class="swatch" style="box-shadow: var(<?= escape($name) ?>)"></span>
								<?php else: ?>
									<span class="swatch is-empty"></span>
								<?php endif ?>
								<code class="name"><?= escape($name) ?></code>
								<code class="value" title="<?= escape(
									(string) $token['value'],
								) ?>"><?= escape((string) $token['value']) ?></code>
							</div>
						<?php endforeach ?>
					</div>
					</details>
				<?php endforeach ?>
			</section>

			<section class="section">
				<h2>Buttons</h2>
				<div class="sample">
					<button type="button" class="cms-button primary">Save</button>
					<button type="button" class="cms-button secondary">Preview</button>
					<button type="button" class="cms-button danger">Delete</button>
					<a class="cms-button secondary" href="#">Link</a>
				</div>
				<div class="sample">
					<button type="button" class="cms-button primary" disabled>Save</button>
					<button type="button" class="cms-button secondary" disabled>Preview</button>
					<button type="button" class="cms-button danger" disabled>Delete</button>
				</div>
			</section>

			<section class="section">
				<h2>Dialogs and action menus</h2>
				<p class="note">The shared PHP/TypeScript shell, including menu-to-dialog focus and nested menus. These samples never submit.</p>
				<form data-meta-owner onsubmit="event.preventDefault()">
					<div class="sample">
						<?php foreach (['start', 'center', 'end'] as $align): ?>
							<button type="button" class="cms-button secondary"
								popovertarget="sample-actions-<?= $align ?>" aria-haspopup="menu">Actions (<?= $align ?>)</button>
							<div id="sample-actions-<?= $align ?>" class="cms-action-menu" popover="auto" data-action-menu data-align="<?= $align ?>">
								<button type="button" data-meta-open><?= \Cosray\Panel\Icon::render('gear') ?> Open dialog</button>
								<a href="#sample-icons">Icon examples</a>
								<hr />
								<button type="button" disabled>Unavailable action</button>
								<button type="button" class="danger">Destructive appearance</button>
							</div>
						<?php endforeach ?>
					</div>
					<dialog class="cms-modal" data-size="compact" data-meta>
						<?php $this->insert('component/modal-header', ['title' => 'Shared dialog']) ?>
						<div class="modal-body">
							<label for="sample-dialog-name">Name</label>
							<input id="sample-dialog-name" class="cms-input" data-dialog-focus value="Live setting" />
							<div data-meta-owner>
								<button type="button" class="cms-button secondary" popovertarget="sample-nested-actions" aria-haspopup="menu">Nested actions</button>
								<div id="sample-nested-actions" class="cms-action-menu" popover="auto" data-action-menu>
									<button type="button" data-meta-open>Open nested dialog</button>
									<button type="button" disabled>Unavailable action</button>
								</div>
								<dialog class="cms-modal" data-size="compact" data-meta>
									<?php $this->insert('component/modal-header', ['title' => 'Nested dialog']) ?>
									<div class="modal-body">Escape returns to the underlying dialog.</div>
									<div class="modal-footer"><button type="button" class="cms-button secondary" data-dialog-close>Close</button></div>
								</dialog>
							</div>
						</div>
						<div class="modal-footer"><button type="button" class="cms-button secondary" data-dialog-close>Close</button></div>
					</dialog>
				</form>
			</section>

			<section class="section" id="sample-icons">
				<h2>Icons</h2>
				<p class="note">Regular Bootstrap artwork shared with Svelte controls. Icons inherit text color and are decorative.</p>
				<div class="sample">
					<?php foreach (['plus', 'plus-circle', 'gear', 'three-dots-vertical', 'x-lg'] as $icon): ?>
						<span><?= \Cosray\Panel\Icon::render($icon) ?> <?= escape($icon) ?></span>
					<?php endforeach ?>
				</div>
			</section>

			<section class="section">
				<h2>Pills and status</h2>
				<div class="sample">
					<span class="cms-count">24 entries</span>
					<?php foreach ($statuses as $status): ?>
						<span class="cms-status is-<?= escape($status) ?>"><?= escape(ucfirst($status)) ?></span>
					<?php endforeach ?>
				</div>
				<div class="sample">
					<span class="cms-status is-published">Published</span>
					<span class="cms-status is-unpublished">Unpublished</span>
				</div>
			</section>

			<section class="section" data-section="controls">
				<h2>Controls</h2>
				<p class="note">
					Every control in every state it can reach, in the classes the panel renders:
					editable, read-only, disabled, invalid.
				</p>
				<div class="sample" data-sample="input:states">
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
				<div class="sample" data-sample="select:states">
					<select class="cms-select" data-sample="select:editable">
						<option>News</option>
						<option>Event</option>
					</select>
					<select class="cms-select" disabled data-sample="select:disabled">
						<option>News</option>
					</select>
					<label class="sample">
						<input class="cms-checkbox" type="checkbox" checked data-sample="checkbox:checked" />
						Checkbox
					</label>
					<label class="sample">
						<input class="cms-checkbox" type="checkbox" disabled data-sample="checkbox:disabled" />
						Disabled
					</label>
				</div>
				<div class="sample" data-sample="switch:states">
					<label class="sample">
						<input type="checkbox" class="cms-switch" checked data-sample="switch:on" /> Switch on
					</label>
					<label class="sample">
						<input type="checkbox" class="cms-switch" data-sample="switch:off" /> Switch off
					</label>
					<label class="sample">
						<input type="checkbox" class="cms-switch" checked disabled data-sample="switch:disabled" />
						Disabled
					</label>
				</div>
				<div class="sample" data-sample="textarea:states">
					<textarea class="cms-textarea" rows="2" data-sample="textarea:editable">Zweisprachige Betreuung für Kinder von 10 Monaten bis 3 Jahren.</textarea>
					<textarea class="cms-textarea" rows="2" readonly data-sample="textarea:readonly">Read-only</textarea>
					<textarea class="cms-textarea" rows="2" disabled data-sample="textarea:disabled">Disabled</textarea>
				</div>
			</section>

			<section class="section">
				<h2>Fields</h2>
				<p class="note">
					Rendered through <code>panel/views/field/*</code> with fixture data — the same
					partials the editor uses, so these cannot fall out of step with it.
				</p>
				<?php // Mirrors the editor: .inner is where its width cap lives, so

				// the sampler shows fields at the width they actually get. ?>
				<div class="cms-node">
					<div class="inner">
						<div class="sheet">
							<?php $this->insert('field/fieldset', [
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
									<?php $this->insert('field/item', [
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
				<h3>Fallback preview</h3>
				<p class="note">
					The German target is empty. The English value is a placeholder only and
					disappears while the input has focus.
				</p>
				<div
					class="cms-node"
					data-content-locale-scope
					data-content-locale="de"
					data-content-locales='<?= escape(json_encode($locales, $jsonFlags)) ?>'>
					<div class="inner">
						<div class="sheet">
							<?php $this->insert('node/content-locales', [
								'locales' => $locales,
								'defaultLocale' => 'de',
								'controlId' => 'styleguide-content-locale',
							]) ?>
							<div class="cms-fields">
								<?php $this->insert('field/item', [
									'field' => $fallbackField,
									'content' => $fallbackContent,
									'locales' => $locales,
									'defaultLocale' => 'de',
									'uid' => 'styleguide',
									'assets' => [],
									'pathSourceFields' => [],
									'globalLocales' => true,
								]) ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="section">
				<h2>Richtext</h2>
				<p class="note">
					The default toolbar, and a field trimmed the way <code>#[Tools]</code> trims it —
					including the source view, which only the second field enables.
				</p>
				<div class="cms-node">
					<div class="inner">
						<div class="sheet">
							<div class="cms-fields">
								<?php foreach ($richtextFields as $field): ?>
									<?php $this->insert('field/item', [
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

			<section class="section">
				<h2>Media</h2>
				<p class="note">
					The image control in both shapes — a single image card and a gallery —
					filled and empty. Fixture assets are inline SVG plates; uploads and the
					library picker are live.
				</p>
				<div class="cms-node">
					<div class="inner">
						<div class="sheet">
							<div class="cms-fields">
								<?php foreach ($mediaFields as $field): ?>
									<?php $this->insert('field/item', [
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

			<section class="section">
				<h2>Entries</h2>
				<p class="note">
					A typed repeater: stored rows collapse to a summary line — thumb, primary and
					secondary text from the first fields with content — and open their form beneath
					it; rows reorder by their grip. Two entry types give two add buttons.
				</p>
				<div class="cms-node">
					<div class="inner">
						<div class="sheet">
							<div class="cms-fields">
								<?php foreach ($entriesFields as $field): ?>
									<?php $this->insert('field/item', [
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

			<section class="section">
				<h2>Blocks</h2>
				<p class="note">
					The typed repeater with a grid. A one-column field is a quiet list; a
					twelve-column field places its blocks on the preview grid — drag an edge
					to resize, or open the gear for the width, rows and indent as numbers
					next to the block's class and id. The + over a block's start corner
					inserts before it; the footer appends. The Story menu has two explicit
					common choices, while Grid uses the first six. More blocks opens the
					complete icon/name catalog in a native modal. Search by label or handle,
					then use arrows and Home/End in the results; Escape cancels without edits.
				</p>
				<div class="cms-node">
					<div class="inner">
						<div class="sheet">
							<div class="cms-fields">
								<?php foreach ($blocksFields as $field): ?>
									<?php $this->insert('field/item', [
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

			<section class="section">
				<h2>Inspector</h2>
				<p class="note">
					Rendered through <code>panel/views/node/inspector.php</code> — toggles, route
					paths, handle and the fact rows of an existing node.
				</p>
				<?php $this->insert('node/inspector', (array) $this->unwrap($inspector)) ?>
			</section>

			<section class="section">
				<h2>Listing</h2>
				<p class="note">
					Rendered through <code>panel/views/collection/row.php</code>, the same partial
					the collection uses. Tree depth, the guide, hover actions and every status
					badge in one place — a real collection rarely shows them together.
				</p>
				<div class="cms-collection">
					<div class="listing">
						<div class="scroll">
							<table
								class="cms-list"
								role="table"
								style="--columns: var(--cms-list-select-width) minmax(12rem, 2fr) minmax(5rem, auto) minmax(5rem, auto) max-content max-content">
								<thead role="rowgroup">
									<tr role="row">
										<th class="col-select" role="columnheader">
											<input type="checkbox" data-bulk-all aria-label="Select all" />
										</th>
										<th role="columnheader"><span class="inner">Title</span></th>
										<th role="columnheader"><span class="inner">Type</span></th>
										<th role="columnheader"><span class="inner">Modified</span></th>
										<th class="col-status" role="columnheader">Status</th>
										<th class="col-actions" role="columnheader"></th>
									</tr>
								</thead>
								<tbody role="rowgroup">
									<?php foreach ($rows as $row): ?>
										<?php $this->insert('collection/row', [
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

			<section class="section">
				<h2>Empty state</h2>
				<div class="cms-collection">
					<div class="listing">
						<div class="empty">
							<div class="icon" aria-hidden="true">⌁</div>
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
