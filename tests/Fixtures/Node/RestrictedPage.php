<?php

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Schema\Permission;
use Cosray\Schema\Render;
use Cosray\Schema\Route;

#[Permission(['read' => 'staff']), Route('/restricted/{uid}'), Render('test-page')]
final class RestrictedPage extends TestPage {}
