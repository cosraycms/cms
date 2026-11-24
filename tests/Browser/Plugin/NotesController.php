<?php

declare(strict_types=1);

namespace Cosray\Tests\Browser\Plugin;

use Celema\Core\Exception\HttpNotFound;
use Cosray\Cms;
use Cosray\Controller\Panel\Panel;

final class NotesController extends Panel
{
	/** @return array<string, mixed> */
	public function note(Cms $cms, NoteSection $sections, string $uid): array
	{
		$node = $cms->node->working($uid) ?? throw new HttpNotFound($this->request);
		$note = $this->formData()['note'] ?? '';

		return ['section' => $sections->noted($node, is_string($note) ? $note : '')];
	}
}
