<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Media::upload
 */
final class MediaUploadTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->authenticateAs('editor');
	}

	/**
	 * The route regex admits dots, so the controller must reject any uid
	 * that could climb out of node/<uid>/ before it reaches the write path.
	 * The guard fires before file handling, so the message is the address
	 * error, not the generic "no file" one.
	 */
	public function testUploadRejectsTraversalUid(): void
	{
		// Each reaches the controller (matches the route regex) yet must be
		// refused: embedded `..`, and non-alphanumeric leading segments.
		foreach (['foo..bar', '-evil', '.evil'] as $uid) {
			$response = $this->makeRequest('POST', "/media/image/node/{$uid}");

			$this->assertResponseStatus(400, $response, "uid {$uid} should be rejected");
			$json = $this->getJsonResponse($response);
			$this->assertFalse($json['ok'], "uid {$uid} should be rejected");
			$this->assertStringContainsString('Upload-Adresse', (string) $json['error']);
		}
	}
}
