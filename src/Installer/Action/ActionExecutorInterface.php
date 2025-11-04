<?php

declare(strict_types=1);

namespace App\Installer\Action;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal for installer wiring; prefer injecting the interface to allow strategy overrides in tests.
 */
interface ActionExecutorInterface
{
    /**
     * @param array<int,array<string,mixed>> $steps
     * @param callable(string,string=,array<string,mixed>=):void $emit
     */
    public function execute(array $steps, ?UploadedFile $package, callable $emit): void;
}
