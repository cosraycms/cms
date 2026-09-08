<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Schema\Allows;
use Cosray\Schema\Columns;
use Cosray\Schema\Common;
use Cosray\Schema\DefaultValue;
use Cosray\Schema\Description;
use Cosray\Schema\Fulltext;
use Cosray\Schema\Hidden;
use Cosray\Schema\Icon;
use Cosray\Schema\Immutable;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Lines;
use Cosray\Schema\Options;
use Cosray\Schema\Placeholder;
use Cosray\Schema\Required;
use Cosray\Schema\Rowspan;
use Cosray\Schema\Syntax;
use Cosray\Schema\Tools;
use Cosray\Schema\Translate;
use Cosray\Schema\Validate;
use Cosray\Schema\When;
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
		$registry->register(Allows::class, new AllowsHandler());
		$registry->register(Common::class, new CommonHandler());
		$registry->register(Label::class, new LabelHandler());
		$registry->register(Icon::class, new IconHandler());
		$registry->register(Description::class, new DescriptionHandler());
		$registry->register(Placeholder::class, new PlaceholderHandler());
		$registry->register(Translate::class, new TranslateHandler());
		$registry->register(Required::class, new RequiredHandler());
		$registry->register(Validate::class, new ValidateHandler());
		$registry->register(DefaultValue::class, new DefaultValueHandler());
		$registry->register(Width::class, new WidthHandler());
		$registry->register(Rowspan::class, new RowspanHandler());
		$registry->register(Lines::class, new LinesHandler());
		$registry->register(Columns::class, new ColumnsHandler());
		$registry->register(Hidden::class, new HiddenHandler());
		$registry->register(Immutable::class, new ImmutableHandler());
		$registry->register(Options::class, new OptionsHandler());
		$registry->register(Limit::class, new LimitHandler());
		$registry->register(Fulltext::class, new FulltextHandler());
		$registry->register(Syntax::class, new SyntaxHandler());
		$registry->register(Tools::class, new ToolsHandler());
		$registry->register(When::class, new WhenHandler());

		return $registry;
	}
}
