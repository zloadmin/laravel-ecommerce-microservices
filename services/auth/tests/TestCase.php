<?php

namespace Tests;

use Ecommerce\Shared\Testing\JwtTestKeys;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        JwtTestKeys::install();
    }
}
