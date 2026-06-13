<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Schema\Columns;
use Cosray\Schema\DefaultValue;
use Cosray\Schema\Description;
use Cosray\Schema\Fulltext;
use Cosray\Schema\Hidden;
use Cosray\Schema\Icon;
use Cosray\Schema\Immutable;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Options;
use Cosray\Schema\Required;
use Cosray\Schema\Rows;
use Cosray\Schema\Syntax;
use Cosray\Schema\Translate;
use Cosray\Schema\Validate;
use Cosray\Schema\Width;

class Registry
{
	/** @var array<class-string, Handler> */
	private array $handlers = [];

	/** @param class-string $schema */
	public function register(string $schema, Handler $handler): void
	{
		$this->handlers[$schema] = $handler;
	}

	public function getHandler(object $schema): ?Handler
	{
		return $this->handlers[$schema::class] ?? null;
	}

	public static function withDefaults(): self
	{
		$registry = new self();
		$registry->register(Label::class, new LabelHandler());
		$registry->register(Icon::class, new IconHandler());
		$registry->register(Description::class, new DescriptionHandler());
		$registry->register(Translate::class, new TranslateHandler());
		$registry->register(Required::class, new RequiredHandler());
		$registry->register(Validate::class, new ValidateHandler());
		$registry->register(DefaultValue::class, new DefaultValueHandler());
		$registry->register(Width::class, new WidthHandler());
		$registry->register(Rows::class, new RowsHandler());
		$registry->register(Columns::class, new ColumnsHandler());
		$registry->register(Hidden::class, new HiddenHandler());
		$registry->register(Immutable::class, new ImmutableHandler());
		$registry->register(Options::class, new OptionsHandler());
		$registry->register(Limit::class, new LimitHandler());
		$registry->register(Fulltext::class, new FulltextHandler());
		$registry->register(Syntax::class, new SyntaxHandler());

		return $registry;
	}
}
