<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a translation loader fails
 */
class LoaderException extends RuntimeException
{
    private string $loaderClass;

    public function __construct(string $loaderClass, string $message, ?Throwable $previous = null)
    {
        $this->loaderClass = $loaderClass;
        $fullMessage = "Loader '{$loaderClass}' failed: {$message}";
        parent::__construct($fullMessage, 0, $previous);
    }

    public function getLoaderClass(): string
    {
        return $this->loaderClass;
    }
}
