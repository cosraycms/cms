<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Cosray\Field\Control;
use Cosray\Field\Control\Registry as Controls;
use Cosray\Locales;
use Cosray\Panel\System;
use Cosray\Richtext\Envelope;
use Cosray\Schema\Tool;

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
	private const int GALLERY_SIZE = 14;

	public function index(Controls $controls): array
	{
		$locales = $this->container->get(Locales::class);
		assert($locales instanceof Locales, 'The locales service must be available');

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
			'richtextFields' => $this->richtextFields($controls),
			'richtextContent' => $this->richtextContent(),
			'mediaFields' => $this->mediaFields($controls),
			'mediaContent' => $this->mediaContent(),
			'mediaAssets' => $this->mediaAssets(),
			// Media controls need the editor bridge for uploads and the
			// library picker; the payload is the one the editor embeds.
			'system' => new System($this->config, $locales)->payload(),
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
	 * Two richtext descriptors: the built-in default toolbar and a field
	 * trimmed the way `#[Tools]` would.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function richtextFields(Controls $controls): array
	{
		$control = Control::richtext()->resolve($controls)->array();

		return [
			[
				'name' => 'rtDefault',
				'label' => 'Richtext — default tools',
				'control' => $control,
				'tools' => array_map(static fn(Tool $tool): string => $tool->value, Tool::DEFAULT),
				'richtextClasses' => (object) [],
				'richtextStyles' => (object) [],
			],
			[
				'name' => 'rtTrimmed',
				'label' => 'Richtext — #[Tools(Bold, Italic, Link, Source)]',
				'control' => $control,
				'tools' => ['bold', 'italic', 'link', 'source'],
				'richtextClasses' => (object) [],
				'richtextStyles' => (object) [],
			],
		];
	}

	/**
	 * Image descriptors in both shapes the control takes: a single image
	 * and a gallery, each once filled and once empty.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function mediaFields(Controls $controls): array
	{
		$control = Control::image()->resolve($controls)->array();
		$single = ['min' => 0, 'max' => 1];
		$many = ['min' => 0, 'max' => -1];

		return [
			[
				'name' => 'cover',
				'label' => 'Cover image',
				'control' => $control,
				'limit' => $single,
				'translate' => true,
				'description' => 'Alt text and title are edited in place; the thumbnail opens the preview.',
			],
			[
				'name' => 'coverEmpty',
				'label' => 'Cover image — empty, required',
				'control' => $control,
				'limit' => $single,
				'required' => true,
			],
			[
				'name' => 'gallery',
				'label' => 'Gallery',
				'control' => $control,
				'limit' => $many,
				'description' => 'Selecting a tile opens the drawer; tiles reorder by drag.',
			],
			[
				'name' => 'galleryEmpty',
				'label' => 'Gallery — empty',
				'control' => $control,
				'limit' => $many,
			],
		];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function mediaContent(): array
	{
		$gallery = [];

		for ($i = 1; $i <= self::GALLERY_SIZE; $i++) {
			$gallery[] = ['uid' => self::galleryUid($i)];
		}

		$gallery[2]['meta'] = ['alt' => ['zxx' => 'Bottling line at full speed']];

		return [
			'cover' => [
				'value' => [
					'zxx' => [[
						'uid' => 'sg-cover',
						'meta' => ['alt' => [
							'en' => 'Copper kettles in the brewhouse',
							'de' => 'Kupferkessel im Sudhaus',
						]],
					]],
				],
			],
			'coverEmpty' => ['value' => ['zxx' => []]],
			'gallery' => ['value' => ['zxx' => $gallery]],
			'galleryEmpty' => ['value' => ['zxx' => []]],
		];
	}

	private static function galleryUid(int $i): string
	{
		return sprintf('sg-gallery-%02d', $i);
	}

	/**
	 * Catalog rows for the fixture uids. The thumbnails are inline SVG
	 * plates in shifting hues, so the samples need no files on disk.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function mediaAssets(): array
	{
		$plate = static function (int $hue, string $filename, int $width, int $height, int $bytes): array {
			$svg = sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 3">'
					. '<rect width="4" height="3" fill="hsl(%d, 35%%, 62%%)"/></svg>',
				$hue,
			);
			$url = 'data:image/svg+xml,' . rawurlencode($svg);

			return [
				'filename' => $filename,
				'url' => $url,
				'thumbUrl' => $url,
				'kind' => 'image',
				'mime' => 'image/jpeg',
				'width' => $width,
				'height' => $height,
				'bytes' => $bytes,
			];
		};

		$assets = ['sg-cover' => $plate(28, 'sudhaus-kupferkessel.jpg', 2400, 1600, 862208)];

		for ($i = 1; $i <= self::GALLERY_SIZE; $i++) {
			$assets[self::galleryUid($i)] = $plate(
				($i * 47) % 360,
				sprintf('brauerei-rundgang-%02d.jpg', $i),
				1800,
				1200,
				300000 + ($i * 41213),
			);
		}

		return $assets;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function richtextContent(): array
	{
		$doc = static fn(string $heading, string $text): array => [
			'type' => 'doc',
			'content' => [
				[
					'type' => 'heading',
					'attrs' => ['level' => 2],
					'content' => [['type' => 'text', 'text' => $heading]],
				],
				['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
			],
		];

		return [
			'rtDefault' => [
				'value' => ['zxx' => $doc('Sudhaus', 'Die neue Maischepfanne wird im Herbst eingebaut.')],
				'format' => Envelope::FORMAT,
				'version' => Envelope::VERSION,
			],
			'rtTrimmed' => [
				'value' => ['zxx' => $doc('Presse', 'Nur Fett, Kursiv, Link und die Quelltextansicht.')],
				'format' => Envelope::FORMAT,
				'version' => Envelope::VERSION,
			],
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
