<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Ingest;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Assets\Ingest::safeFilename
 */
final class IngestFilenameTest extends TestCase
{
	public function testStripsTraversalToBasename(): void
	{
		$this->assertSame('passwd.jpg', Ingest::safeFilename('../../etc/passwd.jpg'));
	}

	public function testStripsNestedDirectoryComponents(): void
	{
		$this->assertSame('pic.png', Ingest::safeFilename('sub/dir/pic.png'));
	}

	public function testPreservesOrdinaryNameWithSpacesAndCase(): void
	{
		$this->assertSame('My Foto.JPG', Ingest::safeFilename('My Foto.JPG'));
	}

	public function testStripsControlCharactersAndNullBytes(): void
	{
		$this->assertSame('evil.png', Ingest::safeFilename("ev\x00il\x1f.png"));
	}

	public function testRejectsPureTraversalToEmptyString(): void
	{
		$this->assertSame('', Ingest::safeFilename('..'));
		$this->assertSame('', Ingest::safeFilename('.'));
		$this->assertSame('', Ingest::safeFilename('../../'));
	}

	public function testTrimsLeadingDotSoDotfilesLoseTheirExtension(): void
	{
		// A leading dot is stripped, so `.htaccess` becomes `htaccess`, which
		// then has no extension and is rejected by the upload allowlist.
		$this->assertSame('htaccess', Ingest::safeFilename('.htaccess'));
	}
}
