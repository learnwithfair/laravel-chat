<?php

namespace RahatulRabbi\TalkBridge\Support;

use Illuminate\Support\Facades\File;

/**
 * UserModelModifier
 *
 * Safely:
 *  - Injects the HasTalkBridgeFeatures trait into the User model
 *  - Removes the trait on uninstall (marker-based, surgical removal)
 *  - Detects whether the model uses $fillable or $guarded
 *  - Adds the last_seen column to $fillable if the model uses it,
 *    OR does nothing if the model uses $guarded = []
 */
class UserModelModifier
{
    protected string $traitFqn     = '\\RahatulRabbi\\TalkBridge\\Traits\\HasTalkBridgeFeatures';
    protected string $markerStart  = '// @talkbridge:start';
    protected string $markerEnd    = '// @talkbridge:end';

    public function __construct(protected string $userModelPath) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Inject trait + handle fillable/guarded.
     */
    public function inject(): void
    {
        $content = $this->read();

        if (! $this->isAlreadyInjected($content)) {
            $content = $this->addTrait($content);
        }

        $content = $this->handleModelProtection($content, 'inject');

        $this->write($content);
    }

    /**
     * Remove trait + reverse fillable changes.
     */
    public function remove(): void
    {
        $content = $this->read();

        $content = $this->removeTrait($content);
        $content = $this->handleModelProtection($content, 'remove');

        $this->write($content);
    }

    public function isAlreadyInjected(?string $content = null): bool
    {
        return str_contains($content ?? $this->read(), $this->markerStart);
    }

    // -------------------------------------------------------------------------
    // Trait injection
    // -------------------------------------------------------------------------

    protected function addTrait(string $content): string
    {
        $block = implode("\n", [
            '    ' . $this->markerStart,
            '    use ' . $this->traitFqn . ';',
            '    ' . $this->markerEnd,
        ]);

        // Insert right after the first opening class brace
        if (preg_match('/\{/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            return substr($content, 0, $pos + 1)
                . "\n" . $block . "\n"
                . substr($content, $pos + 1);
        }

        // Fallback: before last closing brace
        $last = strrpos($content, '}');
        return substr($content, 0, $last) . "\n    " . $block . "\n" . substr($content, $last);
    }

    protected function removeTrait(string $content): string
    {
        $pattern = '/\n?\s*' . preg_quote($this->markerStart, '/') . '.*?' . preg_quote($this->markerEnd, '/') . '\s*\n?/s';
        return preg_replace($pattern, "\n", $content);
    }

    // -------------------------------------------------------------------------
    // Fillable / Guarded detection and patching
    // -------------------------------------------------------------------------

    protected function handleModelProtection(string $content, string $mode): string
    {
        $lastSeenField = config('talkbridge.user_fields.last_seen', 'last_seen_at');

        if ($this->modelUsesGuarded($content)) {
            // $guarded = [] means everything is mass-assignable — nothing to add
            return $content;
        }

        if ($this->modelUsesFillable($content)) {
            if ($mode === 'inject') {
                return $this->addToFillable($content, $lastSeenField);
            }

            if ($mode === 'remove') {
                return $this->removeFromFillable($content, $lastSeenField);
            }
        }

        return $content;
    }

    protected function modelUsesGuarded(string $content): bool
    {
        // Matches: protected $guarded = []; or protected $guarded = ['*'];
        return (bool) preg_match('/\$guarded\s*=\s*\[/', $content);
    }

    protected function modelUsesFillable(string $content): bool
    {
        return str_contains($content, '$fillable');
    }

    protected function addToFillable(string $content, string $field): string
    {
        // Already present?
        if (str_contains($content, "'{$field}'") || str_contains($content, "\"{$field}\"")) {
            return $content;
        }

        // Add to end of fillable array: find last item before ] and append
        return preg_replace_callback(
            '/(\$fillable\s*=\s*\[)(.*?)(\];)/s',
            function ($matches) use ($field) {
                $body    = rtrim($matches[2]);
                $hasComma = str_ends_with($body, ',');
                $sep      = $hasComma ? '' : ',';
                return $matches[1] . $body . $sep . "\n        '{$field}',\n    " . $matches[3];
            },
            $content
        );
    }

    protected function removeFromFillable(string $content, string $field): string
    {
        // Remove the specific line we added
        return preg_replace("/\s*'{$field}',?\n/", "\n", $content);
    }

    // -------------------------------------------------------------------------
    // File helpers
    // -------------------------------------------------------------------------

    protected function read(): string
    {
        if (! File::exists($this->userModelPath)) {
            throw new \RuntimeException("User model not found: {$this->userModelPath}");
        }
        return File::get($this->userModelPath);
    }

    protected function write(string $content): void
    {
        File::put($this->userModelPath, $content);
    }
}
