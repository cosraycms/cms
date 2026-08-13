<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Actor;
use Cosray\Assets\Ingest;
use Cosray\Config;
use Cosray\Exception\IngestError;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssetsIngestTest extends IntegrationTestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/cosray-ingest-' . bin2hex(random_bytes(4));
		mkdir("{$this->dir}/assets", 0o755, true);
	}

	protected function tearDown(): void
	{
		$this->removeDir($this->dir);
		parent::tearDown();
	}

	private function ingest(): Ingest
	{
		return new Ingest($this->ingestConfig(), $this->db());
	}

	private function ingestConfig(): Config
	{
		return $this->config(['path.public' => $this->dir]);
	}

	private function pngBytes(): string
	{
		$image = imagecreatetruecolor(3, 2);
		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}

	public function testIngestsBytesWithoutRequestOrSession(): void
	{
		$result = $this->ingest()->ingest($this->pngBytes(), 'tiny.png', 'image');

		$this->assertTrue($result->created);

		$row = $this->db()->execute(
			'SELECT filename, mime, width, height, creator, meta FROM cms.assets WHERE uid = :uid',
			['uid' => $result->uid()],
		)->one();

		$this->assertSame('tiny.png', $row['filename']);
		$this->assertSame('image/png', $row['mime']);
		$this->assertSame(3, $row['width']);
		$this->assertSame(2, $row['height']);
		$this->assertSame(1, (int) $row['creator']);
		$this->assertSame('{}', (string) $row['meta']);
		$this->assertFileExists("{$this->dir}/assets/{$result->row['key']}");
	}

	public function testDeduplicatesByHash(): void
	{
		$ingest = $this->ingest();
		$first = $ingest->ingest($this->pngBytes(), 'one.png', 'image');
		$second = $ingest->ingest($this->pngBytes(), 'two.png', 'image');

		$this->assertTrue($first->created);
		$this->assertFalse($second->created);
		$this->assertSame($first->uid(), $second->uid());

		$count = $this->db()->execute(
			'SELECT count(*) AS n FROM cms.assets WHERE uid = :uid',
			['uid' => $first->uid()],
		)->one();

		$this->assertSame(1, (int) $count['n']);
	}

	public function testStoresInitialMetaAndActor(): void
	{
		$result = $this->ingest()->ingest(
			$this->pngBytes(),
			'meta.png',
			'image',
			new Actor(1),
			['alt' => ['de' => 'Ein Bild']],
		);

		$row = $this->db()->execute(
			'SELECT meta FROM cms.assets WHERE uid = :uid',
			['uid' => $result->uid()],
		)->one();

		$this->assertSame(['alt' => ['de' => 'Ein Bild']], json_decode((string) $row['meta'], true));
	}

	public function testSanitizesSvgBeforeCataloging(): void
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect/></svg>';
		$result = $this->ingest()->ingest($svg, 'icon.svg', 'image');

		$stored = (string) file_get_contents("{$this->dir}/assets/{$result->row['key']}");

		$this->assertStringNotContainsString('<script', $stored);
		$this->assertStringContainsString('<rect', $stored);
	}

	public function testRejectsDisallowedMimeType(): void
	{
		$this->throws(IngestError::class, 'File type not allowed');
		$this->ingest()->ingest('plain text', 'note.png', 'image');
	}

	public function testRejectsWrongExtension(): void
	{
		$this->throws(IngestError::class, "File extension 'gif' not allowed");
		$this->ingest()->ingest($this->pngBytes(), 'tiny.gif', 'image');
	}

	public function testRejectsEmptyFilename(): void
	{
		$this->throws(IngestError::class, 'No usable filename');
		$this->ingest()->ingest($this->pngBytes(), '../..', 'image');
	}

	public function testRejectsUnknownMediatype(): void
	{
		$this->throws(RuntimeException::class, 'Media type not supported: audio');
		$this->ingest()->ingest($this->pngBytes(), 'tiny.png', 'audio');
	}

	public function testErrorCarriesBothAudiencesMessages(): void
	{
		try {
			$this->ingest()->ingest('plain text', 'note.png', 'image');
			$this->fail('Expected IngestError');
		} catch (IngestError $e) {
			$this->assertSame('File type not allowed: text/plain', $e->getMessage());
			$this->assertSame('text/plain', $e->mime);
			// No translator is active outside a request, where verba falls back
			// to the id. The rendered message is covered through the HTTP path.
			$this->assertSame('media:disallowed-type', $e->userMessage);
		}
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = "{$dir}/{$item}";
			is_dir($path) && !is_link($path) ? $this->removeDir($path) : unlink($path);
		}

		rmdir($dir);
	}
}
