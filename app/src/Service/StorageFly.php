<?php

namespace App\Service;

use League\Flysystem\FilesystemOperator;

class StorageFly
{
    private FilesystemOperator $storage;

    // The variable name $defaultStorage matters: it needs to be the
    // camelCase version of the name of your storage (foo.bar.baz -> fooBarBaz)
    public function __construct(FilesystemOperator $defaultStorage)
    {
        $this->storage = $defaultStorage;
    }

    // ...
}