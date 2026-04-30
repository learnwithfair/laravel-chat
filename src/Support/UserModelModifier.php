<?php

namespace RahatulRabbi\TalkBridge\Support;

use Illuminate\Support\Facades\File;

/**
 * UserModelModifier
 *
 * Safely injects and removes the HasChatFeatures trait
 * from the application's User model file without touching
 * any other code in the file.
 *
 * Injection markers allow precise removal on uninstall:
 *   // @laravel-chat:use-start
 *   use \RahatulRabbi\TalkBridge\Traits\HasChatFeatures;
 *   // @laravel-chat:use-end
 */
class UserModelModifier
{
    protected string $traitFqn    = '\\RahatulRabbi\\TalkBridge\\Traits\\HasChatFeatures';
    protected string $markerStart = '// @laravel-chat:use-start';
    protected string $markerEnd   = '// @laravel-chat:use-end';

    public function __construct(protected string $userModelPath) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Inject the trait into the User model.
     * Returns true if injected, false if already present.
     */
    public function inject(): bool
    {
        $content = $this->read();

        if ($this->isAlreadyInjected($content)) {
            return false;
        }

        $modified = $this->addTrait($content);

        $this->write($modified);

        return true;
    }

    /**
     * Remove the trait from the User model.
     * Returns true if removed, false if was not present.
     */
    public function remove(): bool
    {
        $content = $this->read();

        if (! $this->isAlreadyInjected($content)) {
            return false;
        }

        $modified = $this->removeTrait($content);

        $this->write($modified);

        return true;
    }

    /**
     * Check whether the trait is already injected.
     */
    public function isAlreadyInjected(?string $content = null): bool
    {
        $content = $content ?? $this->read();

        return str_contains($content, $this->markerStart);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected function addTrait(string $content): string
    {
        $traitBlock = implode("\n", [
            '    ' . $this->markerStart,
            '    use ' . $this->traitFqn . ';',
            '    ' . $this->markerEnd,
        ]);

        /*
         * Strategy: find the opening brace of the class body and insert
         * the trait block immediately after it, before any existing content.
         *
         * Works for both:
         *   class User extends Authenticatable
         *   {
         *
         * and:
         *   class User extends Authenticatable {
         */
        if (preg_match('/^(\s*)\{/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $braceOffset = $matches[0][1];
            $braceLength = strlen($matches[0][0]);

            return substr($content, 0, $braceOffset + $braceLength)
                . "\n" . $traitBlock . "\n"
                . substr($content, $braceOffset + $braceLength);
        }

        // Fallback: append before the last closing brace
        $lastBrace = strrpos($content, '}');

        return substr($content, 0, $lastBrace)
            . "\n    " . $traitBlock . "\n"
            . substr($content, $lastBrace);
    }

    protected function removeTrait(string $content): string
    {
        // Remove everything between (and including) the markers, plus surrounding blank lines
        $pattern = '/\n?\s*' . preg_quote($this->markerStart, '/') . '.*?' . preg_quote($this->markerEnd, '/') . '\s*\n?/s';

        return preg_replace($pattern, "\n", $content);
    }

    protected function read(): string
    {
        if (! File::exists($this->userModelPath)) {
            throw new \RuntimeException("User model not found at: {$this->userModelPath}");
        }

        return File::get($this->userModelPath);
    }

    protected function write(string $content): void
    {
        File::put($this->userModelPath, $content);
    }
}
