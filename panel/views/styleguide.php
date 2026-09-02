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
$system = $this->unwrap($system);
$panelBase = (string) $panelBase;
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;

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

// Mirrors editor.php: field/item places each field on the form grid.
$span = static function (mixed $value, int $fallback): string {
	$value = is_int($value) ? $value : $fallback;

	if ($value > 100 || $value <= 0) {
		$value = 100;
	}

	return "span {$value} / span {$value}";
};

$statuses = ['published', 'draft', 'hidden', 'locked'];
$rows = (array) $this->unwrap($rows);

$chevronSvgPath = __DIR__ . '/../icons/chevron.svg';
$chevronSvg = is_file($chevronSvgPath)
	? str_replace(
		'<svg ',
		'<svg class="chevron" aria-hidden="true" focusable="false" ',
		trim((string) file_get_contents($chevronSvgPath)),
	)
	: '';
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

			<section class="section">
				<h2>Controls</h2>
				<div class="sample">
					<input type="text" value="Sudhaus" />
					<input type="text" placeholder="Placeholder" />
					<input type="text" value="Disabled" disabled />
					<select>
						<option>News</option>
						<option>Event</option>
					</select>
					<label class="sample"><input type="checkbox" checked /> Checkbox</label>
				</div>
				<div class="sample">
					<label class="sample"><input type="checkbox" class="cms-switch" checked /> Switch on</label>
					<label class="sample"><input type="checkbox" class="cms-switch" /> Switch off</label>
					<label class="sample"><input type="checkbox" class="cms-switch" checked disabled /> Disabled</label>
				</div>
				<div class="sample">
					<textarea rows="2">Zweisprachige Betreuung für Kinder von 10 Monaten bis 3 Jahren.</textarea>
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
								'span' => $span,
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
										'span' => $span,
									]) ?>
								<?php endforeach ?>
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
										'span' => $span,
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
										'span' => $span,
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
										'span' => $span,
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
											'chevronSvg' => $chevronSvg,
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
