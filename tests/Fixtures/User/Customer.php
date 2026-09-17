<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\User;

use Cosray\Schema\Handle;
use Cosray\Schema\Roles;
use Cosray\User;

#[Handle('shop-customer'), Roles]
class Customer extends User {}
