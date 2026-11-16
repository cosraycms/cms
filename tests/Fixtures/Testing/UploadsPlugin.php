<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Testing;

use Cosray\Plugin\PanelRoutes;
use Cosray\Plugin\Plugin;
use Cosray\Plugin\Registrar;

/** A plugin page that takes an upload, as a plugin's tests drive it. */
final class UploadsPlugin implements Plugin
{
	public function id(): string
	{
		return 'test-uploads';
	}

	public function register(Registrar $cms): void
	{
		$cms->allow('role:admin', 'test-uploads.upload');
		$cms->panel(static function (PanelRoutes $panel): void {
			$panel->post('', [Uploads::class, 'store'], 'store');
		});
	}
}
