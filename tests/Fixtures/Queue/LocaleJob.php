<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Queue;

use Celema\Verba\Verba;
use Cosray\Contract\Job;

final class LocaleJob implements Job
{
	/** The locale of the translator active while the job ran, if any. */
	public static ?string $locale = null;

	public function handle(array $payload): void
	{
		self::$locale = Verba::translator()?->locale;
	}
}
