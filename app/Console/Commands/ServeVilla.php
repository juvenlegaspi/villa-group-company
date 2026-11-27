<?php

namespace App\Console\Commands;

class ServeVilla extends ServeVillaFast
{
    protected $name = 'serve';

    protected $description = 'Serve Villa locally with PHP caching and immediate code-change detection';
}
