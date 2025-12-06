<?php

declare(strict_types=1);

namespace MonkeysLegion\I18n\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when message formatting fails
 */
class MessageFormattingException extends RuntimeException
{
    private string $messageKey;
    
    /** @var array<string, mixed> */
    private array $replacements;

    /**
     * @param string $messageKey
     * @param array<string, mixed> $replacements
     */
    public function __construct(string $messageKey, array $replacements, ?Throwable $previous = null)
    {
        $this->messageKey = $messageKey;
        $this->replacements = $replacements;
        
        $message = "Failed to format message: '{$messageKey}'";
        parent::__construct($message, 0, $previous);
    }

    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReplacements(): array
    {
        return $this->replacements;
    }
}
