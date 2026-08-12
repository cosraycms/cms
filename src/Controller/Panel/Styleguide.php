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
			'content' => $this->content(),
		]);
	}

	/**
	 * Token groups read out of `tokens.css` rather than listed here, so the
	 * page cannot drift from the stylesheet it documents. Only the light
	 * `:root` block is read; the dark block redeclares the same names.
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
