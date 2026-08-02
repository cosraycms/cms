<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Cosray\Contract\HttpDelete;
use Cosray\Contract\HttpGet;
use Cosray\Contract\HttpPost;
use Cosray\Contract\HttpPut;
use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Util\Form;

#[Label('Http Verbs Page')]
class HttpVerbsPage implements HttpDelete, HttpGet, HttpPost, HttpPut, Title
{
	#[Label('Title')]
	public Text $title;

	public function __construct(
		private readonly Factory $factory,
		private readonly Request $request,
	) {}

	public function title(): string
	{
		return 'Http Verbs';
	}

	public function httpGet(): Response
	{
		return $this->answer('get');
	}

	public function httpPost(): Response
	{
		return $this->answer('post');
	}

	public function httpPut(): Response
	{
		return $this->answer('put');
	}

	public function httpDelete(): Response
	{
		return $this->answer('delete');
	}

	private function answer(string $verb): Response
	{
		$body = Form::body($this->request);
		$marker = $body['marker'] ?? '';

		return Response::create($this->factory)->html(
			$verb . ':' . (is_string($marker) ? $marker : ''),
		);
	}
}
