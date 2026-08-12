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
?>

<div id="main" class="page cms-styleguide">
	<header class="topbar">
		<div class="inner">
			<h1>Styleguide</h1>
			<button
				type="button"
				class="cms-button secondary"
				onclick="document.documentElement.dataset.theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'">
				Toggle theme
			</button>
		</div>
	</header>

	<section class="content">
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
				<div class="row">
					<button type="button" class="cms-button primary">Save</button>
					<button type="button" class="cms-button secondary">Preview</button>
					<button type="button" class="cms-button danger">Delete</button>
					<a class="cms-button secondary" href="#">Link</a>
				</div>
				<div class="row">
					<button type="button" class="cms-button primary" disabled>Save</button>
					<button type="button" class="cms-button secondary" disabled>Preview</button>
					<button type="button" class="cms-button danger" disabled>Delete</button>
				</div>
			</section>

			<section class="section">
				<h2>Pills and status</h2>
				<div class="row">
					<span class="count-pill">24 entries</span>
					<span class="type-pill">Article</span>
					<?php foreach ($statuses as $status): ?>
						<span class="status status-<?= escape($status) ?>"><?= escape(ucfirst($status)) ?></span>
					<?php endforeach ?>
				</div>
				<div class="row">
					<span class="cms-published large published">Published</span>
					<span class="cms-published large">Unpublished</span>
				</div>
			</section>

			<section class="section">
				<h2>Controls</h2>
				<div class="row">
					<input type="text" value="Sudhaus" />
					<input type="text" placeholder="Placeholder" />
					<input type="text" value="Disabled" disabled />
					<select>
						<option>News</option>
						<option>Event</option>
					</select>
					<label class="row"><input type="checkbox" checked /> Checkbox</label>
				</div>
				<div class="row">
					<textarea rows="2">Zweisprachige Betreuung für Kinder von 10 Monaten bis 3 Jahren.</textarea>
				</div>
			</section>

			<section class="section">
				<h2>Fields</h2>
				<p class="note">
					Rendered through <code>panel/views/field/*</code> with fixture data — the same
					partials the editor uses, so these cannot fall out of step with it.
				</p>
				<div class="cms-pane-card">
					<div class="field-grid">
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
			</section>

			<section class="section">
				<h2>Empty state</h2>
				<div class="collection-panel">
					<div class="collection-empty">
						<div class="empty-icon" aria-hidden="true">⌁</div>
						<strong>No entries yet</strong>
						<p>Create the first entry to get started.</p>
					</div>
				</div>
			</section>
		</div>
	</section>
</div>
