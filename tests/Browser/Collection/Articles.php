<?php

declare(strict_types=1);

namespace Cosray\Tests\Browser\Collection;

use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;
use Cosray\Tests\Browser\Node\Article;

#[Label('Articles'), Handle('articles'), Types(Article::class), Blueprints(Article::class)]
final class Articles {}
