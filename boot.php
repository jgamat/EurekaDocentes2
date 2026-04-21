<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$kernel = app()->make(Kernel::class);
$kernel->bootstrap();
