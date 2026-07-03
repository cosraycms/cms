<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Controller\Media;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Media::safeFilename
 */
final class MediaFilenameTest extends TestCase
{
	public function testStripsTraversalToBasename(): void
	{
		$this->assertSame('passwd.jpg', Media::safeFilename('../../etc/passwd.jpg'));
	}

	public function testStripsNestedDirectoryComponents(): void
	{
		$this->assertSame('pic.png', Media::safeFilename('sub/dir/pic.png'));
	}

	public function testPreservesOrdinaryNameWithSpacesAndCase(): void
	{
		$this->assertSame('My Foto.JPG', Media::safeFilename('My Foto.JPG'));
	}

	public function testStripsControlCharactersAndNullBytes(): void
	{
		$this->assertSame('evil.png', Media::safeFilename("ev\x00il\x1f.png"));
	}

	public function testRejectsPureTraversalToEmptyString(): void
	{
		$this->assertSame('', Media::safeFilename('..'));
		$this->assertSame('', Media::safeFilename('.'));
		$this->assertSame('', Media::safeFilename('../../'));
	}

	public function testTrimsLeadingDotSoDotfilesLoseTheirExtension(): void
	{
		// A leading dot is stripped, so `.htaccess` becomes `htaccess`, which
		// then has no extension and is rejected by the upload allowlist.
		$this->assertSame('htaccess', Media::safeFilename('.htaccess'));
	}
}
