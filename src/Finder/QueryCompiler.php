<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Context;

final class QueryCompiler
{
	use CompilesField;

	public function __construct(
		private readonly Context $context,
		private readonly array $builtins,
	) {}

	public function compile(string $query): string
	{
		$parser = new QueryParser($this->context, $this->builtins);

		return $this->build($parser->parse($query));
	}

	private function build(array $parserOutput): string
	{
		if (count($parserOutput) === 0) {
			return '';
		}

		$clause = '';

		foreach ($parserOutput as $output) {
			$clause .= $output->get();
		}

		return $clause;
	}
}
