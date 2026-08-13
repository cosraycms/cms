<?php

use function Cosray\escape;

// Development page: strings stay untranslated on purpose, the panel
// catalogs carry what editors see.

if (!$boosted) {
	$this->layout('panel');
}

$tokenGroups = (array) $this->unwrap($tokenGroups);
$fields = (array) $this->unwrap($fields);
$content = (array) $this->unwrap($content);
$locales = (array) $this->unwrap($locales);
$defaultLocale = (string) $defaultLocale;

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

<div id="main" class="page cms-styleguide">
	<header class="head">
			<h1>Styleguide</h1>
			<button
				type="button"
				class="cms-button secondary"
				onclick="document.documentElement.dataset.theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'">
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
								<code class="value"><?= escape((string) $token['value']) ?></code>
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
						<div class="card">
							<div class="cms-fields">
								<?php foreach ($fields as $field): ?>
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
				<h2>Listing</h2>
				<p class="note">
					Rendered through <code>panel/views/collection/row.php</code>, the same partial
					the collection uses. Tree depth, the guide, hover actions and every status
					badge in one place — a real collection rarely shows them together.
				</p>
				<div class="cms-collection">
					<div class="card">
						<div class="scroll">
							<table
								class="cms-list"
								role="table"
								style="--columns: minmax(12rem, 2fr) minmax(5rem, auto) minmax(5rem, auto) minmax(5rem, auto)">
								<thead role="rowgroup">
									<tr role="row">
										<th role="columnheader"><span class="inner">Title</span></th>
										<th role="columnheader"><span class="inner">Type</span></th>
										<th role="columnheader"><span class="inner">Modified</span></th>
										<th class="col-status" role="columnheader">Status</th>
									</tr>
								</thead>
								<tbody role="rowgroup">
									<?php foreach ($rows as $row): ?>
										<?php $this->insert('collection/row', [
											'row' => $row,
											'treeMode' => true,
											'showChildren' => true,
											'chevronSvg' => $chevronSvg,
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
					<div class="card">
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
</div>
