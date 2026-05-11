<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Schema\Columns;
use Celemas\Cms\Schema\DefaultValue;
use Celemas\Cms\Schema\Description;
use Celemas\Cms\Schema\Fulltext;
use Celemas\Cms\Schema\Hidden;
use Celemas\Cms\Schema\Icon;
use Celemas\Cms\Schema\Immutable;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Limit;
use Celemas\Cms\Schema\Options;
use Celemas\Cms\Schema\Required;
use Celemas\Cms\Schema\Rows;
use Celemas\Cms\Schema\Syntax;
use Celemas\Cms\Schema\Translate;
use Celemas\Cms\Schema\TranslateFile;
use Celemas\Cms\Schema\Validate;
use Celemas\Cms\Schema\Width;

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
		$registry->register(TranslateFile::class, new TranslateFileHandler());
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
