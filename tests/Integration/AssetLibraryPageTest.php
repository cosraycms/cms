<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Assets\Library;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @covers \Cosray\Assets\Library
 */
final class AssetLibraryPageTest extends IntegrationTestCase
{
	/** Every file name starts with it, so a search isolates this test's rows. */
	private string $prefix;

	protected function setUp(): void
	{
		parent::setUp();
		$this->prefix = 'lib-' . bin2hex(random_bytes(4)) . '-';
	}

	public function testKindFilterSplitsFilesIntoAudioAndDocuments(): void
	{
		$image = $this->insert('pic.png', 'image/png');
		$video = $this->insert('clip.mp4', 'video/mp4');
		$audio = $this->insert('song.mp3', 'audio/mpeg');
		$document = $this->insert('doc.pdf', 'application/pdf');
		$unknown = $this->insert('blob.bin', null);

		$this->assertSame([$audio], $this->uids($this->page(['audio'])));
		// Unreadable mimes land on document, mirroring Asset::classify().
		$this->assertEqualsCanonicalizing([$document, $unknown], $this->uids($this->page(['document'])));

		$mixed = $this->page(['image', 'audio']);

		$this->assertEqualsCanonicalizing([$image, $audio], $this->uids($mixed));
		$this->assertNotContains($video, $this->uids($mixed));
		$this->assertSame(2, $mixed->total);
		// The counts honor the search but never the kind filter itself.
		$this->assertSame(['image' => 1, 'video' => 1, 'audio' => 1, 'document' => 2], $mixed->counts);
		// A file context accepts every kind, so `file` does not filter.
		$this->assertSame(5, $this->page(['file'])->total);
	}

	public function testSearchMatchesTheFilename(): void
	{
		$this->insert('harbour.png', 'image/png');

		$this->assertSame(1, $this->page()->total);

		$missed = $this->library()->page(q: "{$this->prefix}nomatch");

		$this->assertSame([], $missed->assets);
		$this->assertSame(0, $missed->total);
	}

	public function testSinceCutsOnTheCreatedTimestamp(): void
	{
		$old = $this->insert('old.png', 'image/png', '2020-06-01T12:00:00+00:00');
		$fresh = $this->insert('new.png', 'image/png');

		$page = $this->page(since: Library::since('2021-01-01T00:00:00+00:00'));

		$this->assertSame([$fresh], $this->uids($page));
		$this->assertNotContains($old, $this->uids($page));
		$this->assertSame(1, $page->counts['image']);
		// Invalid input means no cutoff rather than an error.
		$this->assertSame(2, $this->page(since: Library::since('not-a-date'))->total);
	}

	public function testItemsCarryTheUrlsAPickerNeeds(): void
	{
		$image = $this->insert('pic.png', 'image/png');
		$document = $this->insert('doc.pdf', 'application/pdf');
		$items = [];

		foreach ($this->page()->assets as $asset) {
			$items[$asset->uid] = Library::item($asset);
		}

		$dir = substr($image, 0, 2) . "/{$image}";
		$this->assertSame("/assets/{$dir}/{$this->prefix}pic.png", $items[$image]['url']);
		$this->assertSame("/cache/{$dir}/{$this->prefix}pic-thumb.png", $items[$image]['thumbUrl']);
		$this->assertSame("/cache/{$dir}/{$this->prefix}pic-preview.png", $items[$image]['previewUrl']);
		$this->assertSame('image', $items[$image]['kind']);
		// Files without renditions show their own URL.
		$this->assertSame($items[$document]['url'], $items[$document]['thumbUrl']);
		$this->assertSame('file', $items[$document]['kind']);
	}

	private function library(): Library
	{
		return new Library($this->db(), $this->config());
	}

	/** @param list<string> $kinds */
	private function page(array $kinds = [], ?string $since = null): \Cosray\Assets\LibraryPage
	{
		return $this->library()->page(kinds: $kinds, q: $this->prefix, since: $since);
	}

	/** @return list<string> */
	private function uids(\Cosray\Assets\LibraryPage $page): array
	{
		return array_map(static fn($asset): string => $asset->uid, $page->assets);
	}

	/**
	 * A catalog row inserted directly: the kind filters classify by mime
	 * prefix in SQL, so pinning them must not depend on what libmagic
	 * detects for handcrafted bytes.
	 */
	private function insert(string $name, ?string $mime, ?string $created = null): string
	{
		$uid = bin2hex(random_bytes(8));
		$filename = $this->prefix . $name;
		$system = $this->db()->execute("SELECT usr FROM cms.users WHERE type = 'system' LIMIT 1")->one();
		$this->assertNotEmpty($system);

		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, mime, bytes, meta, creator, created)
			VALUES (:uid, 'local', :key, :filename, :mime, 1, '{}'::jsonb, :creator, :created)",
			[
				'uid' => $uid,
				'key' => substr($uid, 0, 2) . "/{$uid}/{$filename}",
				'filename' => $filename,
				'mime' => $mime,
				'creator' => (int) $system['usr'],
				'created' => $created ?? date(DATE_ATOM),
			],
		)->run();

		return $uid;
	}
}
