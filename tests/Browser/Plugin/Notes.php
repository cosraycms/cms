<?php

declare(strict_types=1);

namespace Cosray\Tests\Browser\Plugin;

use Cosray\Plugin\PanelRoutes;
use Cosray\Plugin\Plugin;
use Cosray\Plugin\Registrar;
use Override;

/** A section in the inspector of one node, for the browser tests of plugin sections. */
final class Notes implements Plugin
{
	#[Override]
	public function id(): string
	{
		return 'browser-notes';
	}

	#[Override]
	public function register(Registrar $cms): void
	{
		$cms->templates(__DIR__ . '/views');
		$cms->inspectorSection(NoteSection::class);
		$cms->panel(static function (PanelRoutes $panel): void {
			$panel->post('/{uid}', [NotesController::class, 'note'], 'note')->render('browser-notes:noted');
		});
	}
}
