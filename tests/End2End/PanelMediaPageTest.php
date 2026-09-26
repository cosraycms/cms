<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Panel\Media
 */
final class PanelMediaPageTest extends End2EndTestCase
{
	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->publicDir = sys_get_temp_dir() . '/cosray-media-page-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		$this->app = $this->createApp(['path.public' => $this->publicDir]);
		$this->authenticateAs('editor');
	}

	protected function tearDown(): void
	{
		try {
			$this->removeDir($this->publicDir);
		} finally {
			parent::tearDown();
		}
	}

	public function testMediaPageListsTheCatalogWithFiltersAndInspector(): void
	{
		$image = $this->upload('e2e-page-photo.png', 'image/png');
		$this->upload('e2e-page-notes.pdf', 'application/pdf', 'file');

		$html = $this->html('/cp/media');

		$this->assertHtmlNodeExists('//div[@data-content-locale-scope][@data-content-locale="en"][@data-media]', $html);
		$this->assertHtmlNodeExists("//a[@data-media-tile='{$image}'][contains(@href, 'file={$image}')]", $html);
		$this->assertHtmlNodeExists(
			"//a[@data-media-tile]//span[@class='cms-asset-name'][text()='e2e-page-notes.pdf']",
			$html,
		);
		// The filters render into the shell's rail, with the counts per kind.
		$this->assertHtmlNodeExists(
			'//aside[@class="cms-sidebar"]//*[@data-media-rail]//input[@name="kind[]"][@value="image"]',
			$html,
		);
		$this->assertHtmlNodeExists('//div[@id="media-detail"]//*[@class="cms-media-inspector-empty"]', $html);
		// Uploads report through the bridge the system payload installs.
		$this->assertStringContainsString('id="cosray-system-data"', $html);
		$this->assertStringContainsString('href="/cp/media"', $html);
		$this->assertStringNotContainsString('cosray-media-library', $html);
	}

	public function testFiltersNarrowTheListingAndKeepTheirState(): void
	{
		$this->upload('e2e-filter-photo.png', 'image/png');
		$this->upload('e2e-filter-notes.pdf', 'application/pdf', 'file');

		$images = $this->html('/cp/media', ['kind' => ['image']]);

		$this->assertStringContainsString('e2e-filter-photo.png', $images);
		$this->assertStringNotContainsString('e2e-filter-notes.pdf', $images);
		$this->assertHtmlNodeExists('//input[@name="kind[]"][@value="image"][@checked]', $images);
		// The search form carries the filters along; the reset clears them.
		$this->assertHtmlNodeExists(
			'//form[@class="search"]/input[@type="hidden"][@name="kind"][@value="image"]',
			$images,
		);
		$this->assertHtmlNodeExists('//a[@class="cms-media-reset"][@href="/cp/media"]', $images);

		$search = $this->html('/cp/media', ['q' => 'notes']);

		$this->assertStringContainsString('e2e-filter-notes.pdf', $search);
		$this->assertStringNotContainsString('e2e-filter-photo.png', $search);
		$this->assertHtmlNodeExists('//*[@data-media-rail]//input[@type="hidden"][@name="q"][@value="notes"]', $search);
	}

	public function testTheDetailRendersAloneForTheInspector(): void
	{
		$uid = $this->upload('e2e-detail-photo.png', 'image/png');

		$html = $this->html('/cp/media', ['file' => $uid], ['HX-Request' => 'true', 'HX-Target' => 'div#media-detail']);

		$this->assertStringStartsWith('<div id="media-detail"', trim($html));
		$this->assertStringNotContainsString('cms-masthead', $html);
		$this->assertHtmlNodeExists("//form[@hx-post='/cp/media/{$uid}?file={$uid}']", $html);
		$this->assertHtmlNodeExists('//div[@class="variant"][@data-locale="en"]//input[@name="meta[alt][en]"]', $html);
		$this->assertHtmlNodeExists(
			'//div[@class="variant"][@data-locale="de"][@hidden]//input[@name="meta[alt][de]"]',
			$html,
		);
		$this->assertHtmlNodeExists('//input[@type="hidden"][@name="meta[focal][x]"]', $html);
		$this->assertHtmlNodeExists(
			"//dialog[@data-media-delete-dialog]//form[@action='/cp/media/{$uid}/delete?file={$uid}']",
			$html,
		);
	}

	public function testTheNextPageSwapsInAfterTheTiles(): void
	{
		for ($i = 0; $i < 61; $i++) {
			$this->upload(sprintf('e2e-page-%02d.pdf', $i), 'application/pdf', 'file');
		}

		$first = $this->html('/cp/media', ['q' => 'e2e-page-']);

		$this->assertSame(60, substr_count($first, 'data-media-tile='));
		$this->assertHtmlNodeExists('//div[@data-media-grid]/a[@id="media-more"][contains(@href, "page=2")]', $first);

		$next = $this->html(
			'/cp/media',
			['q' => 'e2e-page-', 'page' => '2'],
			[
				'HX-Request' => 'true',
				'HX-Target' => 'a#media-more',
			],
		);

		$this->assertSame(1, substr_count($next, 'data-media-tile='));
		$this->assertStringNotContainsString('id="media-more"', $next);
		$this->assertStringNotContainsString('cms-masthead', $next);
	}

	public function testThePickerHandsTilesTheirAssetAndPagesInPlace(): void
	{
		$image = $this->upload('e2e-picker-photo.png', 'image/png');
		$this->upload('e2e-picker-notes.pdf', 'application/pdf', 'file');

		$html = $this->html('/cp/media/picker', ['kind' => 'image', 'file' => $image]);

		$this->assertStringStartsWith('<div class="cms-library" data-media-picker>', trim($html));
		$this->assertStringNotContainsString('cms-masthead', $html);
		$this->assertStringNotContainsString('e2e-picker-notes.pdf', $html);
		$this->assertHtmlNodeExists('//form[@hx-get="/cp/media/picker"]/input[@name="kind"][@value="image"]', $html);
		$document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
		$tile = $document->querySelector('button.cms-asset-tile.active[data-pick]');
		$this->assertNotNull($tile);
		$item = json_decode((string) $tile->getAttribute('data-pick'), true);
		$this->assertSame($image, $item['uid']);
		$this->assertSame('e2e-picker-photo.png', $item['filename']);
		$this->assertSame('image', $item['kind']);

		$more = $this->html(
			'/cp/media/picker',
			['kind' => 'image', 'page' => '2'],
			[
				'HX-Request' => 'true',
				'HX-Target' => 'a#media-more',
			],
		);

		$this->assertStringNotContainsString('data-media-picker', $more);
		$this->assertStringNotContainsString('data-media-tile', $more);
	}

	public function testSavingMetaKeepsTheFormShapeAndConfirms(): void
	{
		$uid = $this->upload('e2e-save-photo.png', 'image/png');

		$response = $this->makeRequest('POST', "/cp/media/{$uid}", [
			'headers' => ['Content-Type' => 'application/x-www-form-urlencoded', 'HX-Request' => 'true'],
			'body' => http_build_query([
				'meta' => [
					'alt' => ['en' => 'A single pixel', 'de' => 'Ein Pixel'],
					'caption' => ['en' => ''],
					'credit' => ' Studio ',
					'focal' => ['x' => '0.25', 'y' => '0.75'],
				],
			]),
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists('//span[@class="cms-detail-saved"]', $html);
		$this->assertHtmlNodeExists('//input[@name="meta[alt][de]"][@value="Ein Pixel"]', $html);
		$meta = json_decode(
			(string) $this->db()->execute(
				'SELECT meta FROM cms.assets WHERE uid = :uid',
				['uid' => $uid],
			)->one()['meta'],
			true,
		);
		$this->assertEquals(['en' => 'A single pixel', 'de' => 'Ein Pixel'], $meta['alt']);
		$this->assertSame('Studio', $meta['credit']);
		$this->assertEquals(['x' => 0.25, 'y' => 0.75], $meta['focal']);
		$this->assertArrayNotHasKey('caption', $meta);
	}

	public function testDeletingReturnsToTheListingOrNamesWhatStillUsesTheFile(): void
	{
		$gone = $this->upload('e2e-delete-page-gone.png', 'image/png');
		$used = $this->upload('e2e-delete-page-used.png', 'image/png');
		$this->db()->execute(
			"INSERT INTO cms.asset_references (owner_type, owner_uid, asset_uid)
			VALUES ('node', 'e2e-media-page-owner', :uid)",
			['uid' => $used],
		)->run();

		$deleted = $this->makeRequest('POST', "/cp/media/{$gone}/delete", [
			'query' => ['kind' => 'image', 'file' => $gone],
			'headers' => ['HX-Request' => 'true'],
		]);

		$this->assertResponseOk($deleted);
		$this->assertSame(
			['path' => '/cp/media?kind=image', 'target' => '#main'],
			json_decode($deleted->getHeaderLine('HX-Location'), true),
		);
		$this->assertSame(
			[],
			$this->db()->execute(
				'SELECT 1 FROM cms.assets WHERE uid = :uid',
				['uid' => $gone],
			)->all(),
		);

		$blocked = $this->makeRequest('POST', "/cp/media/{$used}/delete", [
			'headers' => ['HX-Request' => 'true'],
		]);

		$this->assertResponseOk($blocked);
		$html = $this->getHtmlResponse($blocked);
		$this->assertHtmlNodeExists(
			'//div[@class="cms-detail-blocked"]//li[contains(., "e2e-media-page-owner")]',
			$html,
		);

		$plain = $this->makeRequest('POST', '/cp/media/nope-no-such-uid/delete');
		$this->assertSame(404, $plain->getStatusCode());
	}

	public function testTheInspectorArrivesInItsRememberedState(): void
	{
		$html = $this->html('/cp/media', [], [], ['cosray_inspector' => 'collapsed']);

		$this->assertHtmlNodeExists('//aside[@class="cms-inspector"][@data-collapsed]', $html);
	}

	public function testMediaPageRequiresAuthentication(): void
	{
		$response = $this->makeRequest('GET', '/cp/media', ['authToken' => '']);

		$this->assertSame(303, $response->getStatusCode());
		$this->assertStringStartsWith('/cp/login', $response->getHeaderLine('Location'));
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, string> $headers
	 * @param array<string, string> $cookies
	 */
	private function html(string $path, array $query = [], array $headers = [], array $cookies = []): string
	{
		$response = $this->makeRequest('GET', $path, array_filter([
			'query' => $query,
			'headers' => $headers,
			'cookies' => $cookies,
		]));
		$this->assertResponseOk($response);

		return $this->getHtmlResponse($response);
	}

	private function upload(string $filename, string $mediaType, string $kind = 'image'): string
	{
		$json = $this->getJsonResponse($this->makeRequest('POST', "/media/{$kind}", [
			'files' => ['file' => $this->uploadedFile($this->contents($filename, $mediaType), $filename, $mediaType)],
		]));
		$this->assertTrue($json['ok'], (string) ($json['error'] ?? ''));

		return (string) $json['uid'];
	}

	/** Distinct bytes per file: the catalog dedupes uploads by content hash. */
	private function contents(string $filename, string $mediaType): string
	{
		if ($mediaType === 'application/pdf') {
			return "%PDF-1.4\n% {$filename}\n%%EOF\n";
		}

		$image = imagecreatetruecolor(1, 1);
		imagesetpixel($image, 0, 0, crc32($filename) & 0xffffff);
		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}

	private function uploadedFile(string $contents, string $filename, string $mediaType): UploadedFileInterface
	{
		$stream = $this->factory()->streamFactory()->createStream($contents);

		return $this->factory()->uploadedFile($stream, strlen($contents), UPLOAD_ERR_OK, $filename, $mediaType);
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($dir);
	}
}
