<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Celemas\Verba\Translator;
use Celemas\Verba\Verba;
use Cosray\Locales;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class AddLocale implements Middleware
{
	public function __construct(
		protected Locales $locales,
	) {}

	public function process(Request $request, Handler $handler): Response
	{
		$locale = $this->locales->negotiate($request);
		$translator = new Translator($locale->id, $this->locales->catalogs());
		Verba::activate($translator);

		try {
			return $handler->handle(
				$request
					->withAttribute('locales', $this->locales)
					->withAttribute('locale', $locale)
					->withAttribute('translator', $translator)
					->withAttribute('defaultLocale', $this->locales->getDefault()),
			);
		} finally {
			Verba::deactivate();
		}
	}
}
