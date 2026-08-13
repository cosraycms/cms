<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Tests\TestCase;

/**
 * The classification derived from an asset's mime type.
 *
 * @internal
 *
 * @coversNothing
 */
final class AssetKindTest extends TestCase
{
	public function testClassifiesByMimeFamily(): void
	{
		$this->assertSame('image', Asset::classify('image/png'));
		$this->assertSame('image', Asset::classify('image/svg+xml'));
		$this->assertSame('video', Asset::classify('video/mp4'));
		$this->assertSame('file', Asset::classify('application/pdf'));
	}

	/** Audio has no kind of its own yet, so it lands with the documents. */
	public function testAudioCountsAsFile(): void
	{
		$this->assertSame('file', Asset::classify('audio/mpeg'));
	}

	/**
	 * Legacy rows for referenced-but-missing files carry no mime. They are
	 * 404s either way, and `file` keeps the derivation in step with
	 * `list.tpql`, whose `mime LIKE` never matches NULL.
	 */
	public function testUnknownMimeCountsAsFile(): void
	{
		$this->assertSame('file', Asset::classify(null));
	}

	/** Bytes sniffed as an image are an image, whichever route stored them. */
	public function testKindFollowsTheBytesNotTheUploadRoute(): void
	{
		$asset = new Asset(
			uid: 'aabbccddeeff1',
			disk: 'local',
			key: 'aa/aabbccddeeff1/logo.png',
			filename: 'logo.png',
			mime: 'image/png',
		);

		$this->assertSame('image', $asset->kind);
	}
}
