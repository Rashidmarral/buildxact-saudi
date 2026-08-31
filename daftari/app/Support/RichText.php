<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The only path by which admin-authored rich text (CmsSection/
 * CmsSectionItem body fields, edited via the minimal contenteditable
 * toolbar in admin/cms/edit.blade.php) is allowed to reach the public site
 * as raw HTML. Sanitizing on save (CmsController::updateSection) is the
 * authoritative defense; partials that echo a body field also run it
 * through here again before {!! !!}, so a value written directly to the
 * database by any other path (a future import script, a raw DB edit)
 * can never carry an unsanitized payload to a visitor's browser.
 *
 * Deliberately narrow: bold/italic/underline, links, and lists — enough
 * for marketing copy, nothing that could carry a script, inline style, or
 * event handler. No headings/images/tables here; images belong in the
 * section's own image_path/upload, not embedded in body text.
 */
class RichText
{
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $config = (new HtmlSanitizerConfig())
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('b')
            ->allowElement('strong')
            ->allowElement('i')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('a', ['href'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('a', 'target', '_blank')
            ->allowLinkSchemes(['https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->withMaxInputLength(20000);

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    /**
     * Renders a CmsSection/CmsSectionItem body field for {!! !!} output —
     * handles both real HTML from the rich-text editor (re-sanitized here
     * as defense in depth; the authoritative pass already happened on
     * save) and legacy plain text seeded before the editor existed, which
     * used a blank line between paragraphs instead of real <p> tags.
     */
    public static function toHtml(?string $body): ?string
    {
        if ($body === null || trim($body) === '') {
            return null;
        }

        if (str_contains($body, '<') && str_contains($body, '>')) {
            return self::sanitize($body);
        }

        return collect(explode("\n\n", $body))
            ->map(fn ($paragraph) => trim($paragraph))
            ->filter(fn ($paragraph) => $paragraph !== '')
            ->map(fn ($paragraph) => '<p>'.e($paragraph).'</p>')
            ->implode('');
    }
}
