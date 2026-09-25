<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;
use Cosray\Tests\Fixtures\Node\TestArticle;
use Cosray\Tests\Fixtures\Node\TestPage;

#[
	Label('Test mixed'),
	Handle('test-mixed'),
	Types('test-page', 'test-article'),
	Blueprints(TestPage::class, TestArticle::class),
]
final class TestMixedCollection {}
