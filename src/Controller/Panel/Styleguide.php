<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

/**
 * Renders every panel component against the current stylesheets, so rare
 * states (empty, error, disabled, truncating, both themes) can be checked
 * without hunting for content that happens to produce them.
 *
 * Registered only when `app.debug` is on.
 */
final class Styleguide extends Panel
{
	protected const string AREA = 'styleguide';

	public function index(): array
	{
		return $this->context([
			'tokenGroups' => $this->tokenGroups(),
			'locales' => [
				['id' => 'en', 'title' => 'English'],
				['id' => 'de', 'title' => 'Deutsch'],
			],
			'defaultLocale' => 'en',
			'fields' => $this->fields(),
			'fieldset' => $this->fieldset(),
			'content' => $this->content(),
			'rows' => $this->rows(),
			'inspector' => $this->inspector(),
		]);
	}

	/**
	 * Token groups read out of `tokens.css` rather than listed here, so the
	 * page cannot drift from the stylesheet it documents. Only the `:root`
	 * block is read — the rules after it force a theme, they declare no
	 * tokens.
	 *
	 * @return list<array{title: string, open: bool, tokens: list<array{name: string, value: string, swatch: bool}>}>
	 */
	private function tokenGroups(): array
	{
		$path = $this->panelDir . '/styles/tokens.css';

		if (!is_file($path)) {
			return [];
		}

		$groups = [];
		$title = 'Tokens';
		$declaration = '';
		$comment = [];
		$inRoot = false;
		$inComment = false;
		$inNote = false;

		foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
			$line = trim($line);

			if (!$inRoot) {
				$inRoot = $line === ':root {';

				continue;
			}

			if ($line === '}') {
				break;
			}

			// A `/**` block names a group; a plain `/*` block is a note about
			// the token below it and carries no heading.
			if ($inNote || str_starts_with($line, '/*') && !str_starts_with($line, '/**')) {
				$inNote = !str_ends_with($line, '*/');

				continue;
			}

			if ($inComment || str_starts_with($line, '/**')) {
				$inComment = !str_ends_with($line, '*/');
				$text = trim(trim($line, '/*'));

				if ($text !== '' && $comment === []) {
					$comment[] = $text;
				}

				if (!$inComment) {
					$title = $comment[0] ?? $title;
					$comment = [];
				}

				continue;
			}

			// A declaration may span several lines (multi-line color-mix()).
			$declaration = trim($declaration . ' ' . $line);

			if (!str_ends_with($declaration, ';') || !str_starts_with($declaration, '--cms-')) {
				if (str_ends_with($declaration, ';')) {
					$declaration = '';
				}

				continue;
			}

			[$name, $value] = explode(':', $declaration, 2);
			$name = trim($name);
			$declaration = '';

			$groups[$title][] = [
				'name' => $name,
				'value' => trim(rtrim(trim($value), ';')),
				'swatch' => str_contains($name, 'color'),
			];
		}

		// Primitives are the longest and least consulted group — 55 spacing
		// steps ahead of everything worth looking up — so they start collapsed.
		return array_map(
			static fn(string $title, array $tokens): array => [
				'title' => $title,
				'open' => !str_starts_with($title, 'Primitives'),
				'tokens' => $tokens,
			],
			array_keys($groups),
			array_values($groups),
		);
	}

	/**
	 * Field descriptors in the shape the editor passes to `field/item`.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function fields(): array
	{
		return [
			[
				'name' => 'title',
				'label' => 'Title',
				'control' => ['name' => 'text', 'props' => ['placeholder' => 'Untitled']],
				'required' => true,
				'description' => 'Shown in listings, search results and when the page is shared.',
			],
			[
				'name' => 'teaser',
				'label' => 'Teaser',
				'control' => ['name' => 'textarea', 'props' => []],
				'translate' => true,
			],
			[
				'name' => 'category',
				'label' => 'Category',
				'control' => ['name' => 'option', 'props' => []],
				'options' => ['news', 'event', 'press'],
			],
			[
				'name' => 'weight',
				'label' => 'Weight',
				'control' => ['name' => 'number', 'props' => []],
				'width' => 50,
			],
			[
				'name' => 'featured',
				'label' => 'Featured',
				'control' => ['name' => 'checkbox', 'props' => []],
				'width' => 50,
			],
			[
				'name' => 'locked',
				'label' => 'Locked',
				'control' => ['name' => 'text', 'props' => []],
				'immutable' => true,
				'description' => 'Immutable fields render disabled.',
			],
			[
				'name' => 'overflow',
				'label' => 'A label long enough to find out what happens when it does not fit',
				'control' => ['name' => 'text', 'props' => []],
			],
		];
	}

	/**
	 * A fieldset descriptor in the shape the editor passes to `field/fieldset`;
	 * its members come out of `fields()`, the rest render as a loose run below
	 * it — the two section forms the editor sheet knows.
	 *
	 * @return array<string, mixed>
	 */
	private function fieldset(): array
	{
		return [
			'name' => 'basics',
			'label' => 'Basics',
			'description' => 'Title and teaser are shown in listings, search results and when the page is shared.',
			'fields' => ['title', 'teaser'],
		];
	}

	/**
	 * Everything `node/inspector` needs, in the shape the editor passes it:
	 * toggles, route paths per locale, the handle and the fact rows of an
	 * existing node.
	 *
	 * @return array<string, mixed>
	 */
	private function inspector(): array
	{
		return [
			'node' => [
				'uid' => 'node-4f21c8',
				'handle' => 'sudhaus',
				'published' => true,
				'hidden' => false,
				'paths' => ['en' => '/en/brewery/brewhouse', 'de' => '/brauerei/sudhaus'],
				'type' => ['label' => 'Page'],
			],
			'locales' => [
				['id' => 'en', 'title' => 'English'],
				['id' => 'de', 'title' => 'Deutsch'],
			],
			'defaultLocale' => 'en',
			'routable' => true,
			'renderable' => true,
			'pathsUrl' => null,
			'generatedPaths' => [],
			'meta' => ['created' => 'Aug 11, 2026', 'editor' => 'M. Keller'],
		];
	}

	/**
	 * Listing rows in the shape `collection/row` expects, covering the states a
	 * real collection rarely shows all at once: tree depth, a collapsed branch,
	 * the last child of a branch, and each status.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function rows(): array
	{
		$row = static fn(array $overrides): array => array_merge([
			'uid' => 'styleguide',
			'depth' => 0,
			'last' => false,
			'expanded' => false,
			'published' => true,
			'hasChildren' => false,
			'childrenUrl' => null,
			'focusedChildrenUrl' => null,
			'childCreateLinks' => [],
			'status' => [['kind' => 'published', 'label' => 'Published']],
			'cells' => [],
		], $overrides);

		$cells = static fn(string $title, string $type, string $changed): array => [
			['class' => 'is-bold', 'label' => 'Title', 'value' => $title, 'editUrl' => '#'],
			['class' => '', 'label' => 'Type', 'value' => $type, 'editUrl' => null],
			['class' => '', 'label' => 'Modified', 'value' => $changed, 'editUrl' => null],
		];

		return [
			$row([
				'expanded' => true,
				'hasChildren' => true,
				'childrenUrl' => '#',
				'focusedChildrenUrl' => '#',
				'childCreateLinks' => [['url' => '#', 'name' => 'Page']],
				'cells' => $cells('Brauerei', 'Page', 'Aug 11, 2026, 10:25 PM'),
			]),
			$row([
				'depth' => 1,
				'childrenUrl' => '#',
				'status' => [['kind' => 'draft', 'label' => 'Draft']],
				'published' => false,
				'cells' => $cells('Sudhaus', 'Page', 'Aug 11, 2026, 10:25 PM'),
			]),
			$row([
				'depth' => 2,
				'last' => true,
				'status' => [['kind' => 'hidden', 'label' => 'Hidden']],
				'published' => false,
				'cells' => $cells(
					'A title long enough that it has to be cut off somewhere',
					'Page',
					'Aug 11, 2026, 10:25 PM',
				),
			]),
			$row([
				'depth' => 1,
				'last' => true,
				'status' => [['kind' => 'locked', 'label' => 'Locked']],
				'cells' => $cells('Presse', 'Page', 'Aug 11, 2026, 10:25 PM'),
			]),
		];
	}

	/**
	 * @return array<string, array{value: array<string, mixed>}>
	 */
	private function content(): array
	{
		return [
			'title' => ['value' => ['zxx' => 'Sudhaus wird modernisiert']],
			'teaser' => ['value' => [
				'en' => 'The brewhouse gets a new mash tun.',
				'de' => 'Das Sudhaus bekommt eine neue Maischepfanne.',
			]],
			'category' => ['value' => ['zxx' => 'news']],
			'weight' => ['value' => ['zxx' => 20]],
			'featured' => ['value' => ['zxx' => true]],
			'locked' => ['value' => ['zxx' => 'node-4f21c8']],
			'overflow' => ['value' => ['zxx' => '']],
		];
	}
}
