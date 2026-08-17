<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature test merender root view (app.blade.php) yang memakai @vite. Di CI
        // aset frontend tidak di-build pada job backend, jadi manifest Vite tak ada.
        // withoutVite() membuat @vite mengembalikan string kosong sehingga render
        // halaman tidak bergantung pada hasil build.
        $this->withoutVite();
    }
}
