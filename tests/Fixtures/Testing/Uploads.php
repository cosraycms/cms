<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Testing;

use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Cosray\Controller\Panel\Panel;
use Cosray\Middleware\Permission;
use Psr\Http\Message\UploadedFileInterface;

final class Uploads extends Panel
{
	#[Permission('test-uploads.upload')]
	public function store(Factory $factory): Response
	{
		$file = $this->request->files()['file'] ?? null;

		return Response::create($factory)->json([
			'user' => $this->currentUser()->email,
			'note' => $this->formData()['note'] ?? null,
			'name' => $file instanceof UploadedFileInterface ? $file->getClientFilename() : null,
			'content' => $file instanceof UploadedFileInterface ? (string) $file->getStream() : null,
		]);
	}
}
