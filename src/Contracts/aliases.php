<?php

declare(strict_types=1);

// Backward compatibility: old Contracts/ namespace → new Contract/
namespace MonkeysLegion\I18n\Contracts;

// Re-export as aliases
class_alias(\MonkeysLegion\I18n\Contract\LoaderInterface::class, LoaderInterface::class);
class_alias(\MonkeysLegion\I18n\Contract\LocaleDetectorInterface::class, LocaleDetectorInterface::class);
class_alias(\MonkeysLegion\I18n\Contract\MessageFormatterInterface::class, MessageFormatterInterface::class);
