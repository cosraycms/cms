<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Celema\Quma\Database;
use Cosray\Assets\Asset;
use Cosray\Assets\Library;
use Cosray\Assets\Meta;
use Cosray\Locales;
use Cosray\Panel\MediaScreen;
use Cosray\Panel\System;
use Cosray\References\Usage;

/**
 * The media screen: the asset catalog as a filterable grid with a detail
 * inspector. The URL holds the whole state (MediaScreen); htmx asks for a
 * fragment by the region it swaps: `#media-detail` for the inspector,
 * `#media-more` for the next page of tiles.
 */
final class Media extends Panel
{
	protected const string AREA = 'media';

	public function index(Database $db, Locales $locales): array
	{
		$screen = $this->screen();

		if ($this->target() === 'media-detail') {
			return ['part' => 'detail', ...$this->detail($db, $locales, $screen)];
		}

		$listing = new Library($db, $this->config)->page(
			kinds: $screen->kinds,
			q: $screen->q,
			since: Library::rangeSince($screen->range),
			page: $screen->page,
		);

		if ($this->target() === 'media-more') {
			return [
				'part' => 'tiles',
				'screen' => $screen,
				'listing' => $listing,
				'localeId' => $this->localeId(),
			];
		}

		$system = new System($this->config, $locales)->payload();

		return $this->context([
			'part' => 'page',
			'screen' => $screen,
			'listing' => $listing,
			'detail' => $screen->file === null ? null : $this->detail($db, $locales, $screen),
			'contentLocales' => $system['locales'],
			'defaultLocale' => $system['defaultLocale'],
			'inspectorCollapsed' => $this->request->cookie('cosray_inspector', '') === 'collapsed',
			// Feeds the window.Cosray bridge, whose toasts report upload failures.
			'system' => $system,
			'rail' => true,
		]);
	}

	/**
	 * Assets whose filename matches `q`, as JSON for the menu editor's
	 * asset picker: the listing's first page, narrowed by `kind`.
	 */
	public function search(Database $db, Factory $factory): Response
	{
		$kind = $this->request->param('kind', '');
		$q = $this->request->param('q', '');
		$page = new Library($db, $this->config)->page(
			kinds: Library::filterKinds(is_string($kind) ? $kind : ''),
			q: is_string($q) ? $q : '',
		);

		return Response::create($factory)->json([
			'ok' => true,
			'assets' => array_map(Library::item(...), $page->assets),
		]);
	}

	/**
	 * The library as controls embed it to pick an asset: a search and the
	 * tile grid, whose tiles carry the asset they stand for. `kind` narrows
	 * the listing, `file` marks the current pick.
	 */
	public function picker(Database $db): array
	{
		$screen = MediaScreen::fromParams($this->panelPath() . '/media/picker', $this->request->params());

		return [
			'part' => $this->target() === 'media-more' ? 'tiles' : 'picker',
			'picker' => true,
			'screen' => $screen,
			'listing' => new Library($db, $this->config)->page(
				kinds: $screen->kinds,
				q: $screen->q,
				page: $screen->page,
			),
			'localeId' => $this->localeId(),
		];
	}

	/**
	 * Saves the editable meta slice from the detail form: localized alt,
	 * title and caption, the credit line and an image's focal point.
	 */
	public function save(Database $db, Locales $locales, string $uid): array
	{
		$row = $db->assets->byUid(['uid' => $uid])->first();

		if (!$row) {
			throw new HttpNotFound($this->request);
		}

		$asset = Asset::fromRow($row, $this->config);
		$input = $this->formData()['meta'] ?? [];
		$meta = Meta::apply(
			$asset->meta,
			is_array($input) ? $input : [],
			$this->localeIds($locales),
			$asset->kind === 'image',
		);
		$db->assets->updateMeta(['uid' => $uid, 'meta' => json_encode($meta)])->run();

		return [
			'part' => 'detail',
			'saved' => true,
			...$this->detail($db, $locales, $this->screen()->withFile($uid)),
		];
	}

	/**
	 * Deletes an unreferenced asset and returns to the listing; an asset in
	 * use stays, and its detail names what still uses it.
	 */
	public function delete(Database $db, Locales $locales, Factory $factory, string $uid): array|Response
	{
		$screen = $this->screen()->withFile($uid);
		$owners = new Library($db, $this->config)->delete($uid);

		if ($owners === null) {
			throw new HttpNotFound($this->request);
		}

		if ($owners !== []) {
			return ['part' => 'detail', 'blocked' => $owners, ...$this->detail($db, $locales, $screen)];
		}

		$url = $screen->url(['file' => null]);

		if ($this->request->hasHeader('HX-Request')) {
			return Response::create($factory)->header(
				'HX-Location',
				(string) json_encode(['path' => $url, 'target' => '#main'], JSON_UNESCAPED_SLASHES),
			);
		}

		return Response::create($factory)->redirect($url, 303);
	}

	private function screen(): MediaScreen
	{
		return MediaScreen::fromParams($this->panelPath() . '/media', $this->request->params());
	}

	/**
	 * The inspector's view of the selected file; a file that went away
	 * leaves the asset null.
	 *
	 * @return array<string, mixed>
	 */
	private function detail(Database $db, Locales $locales, MediaScreen $screen): array
	{
		$uid = $screen->file ?? '';
		$row = $uid === '' ? null : $db->assets->byUid(['uid' => $uid])->first();
		$system = new System($this->config, $locales)->payload();

		return [
			'screen' => $screen,
			'localeId' => $this->localeId(),
			'asset' => $row ? Asset::fromRow($row, $this->config) : null,
			'usage' => $row ? new Usage($db)->forAsset($uid) : [],
			'contentLocales' => $system['locales'],
			'defaultLocale' => $system['defaultLocale'],
		];
	}

	/** @return list<string> */
	private function localeIds(Locales $locales): array
	{
		$ids = [];

		foreach ($locales as $locale) {
			$ids[] = $locale->id;
		}

		return $ids;
	}
}
