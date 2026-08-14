<?php

declare(strict_types=1);

namespace nlxNeosContent\Neos\HeadTag;

use DOMDocument;
use DOMElement;

/**
 * Parses a single raw HTML tag string (as delivered by Neos in the `head` JSON
 * array) into its tag name and attributes, so NeosHeadDataFactory can match it
 * against configured allow-rules.
 */
final class HeadTagParser
{
    public function parse(string $tagHtml): ?ParsedHeadTag
    {
        if (trim($tagHtml) === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        // LIBXML_NOERROR|LIBXML_NOWARNING already suppress parse warnings at
        // the libxml level; deliberately NOT using libxml_use_internal_errors()
        // here, since it mutates process-wide (not thread-local) global state
        // and this method runs very frequently (once per raw head tag) under
        // FrankenPHP's multi-threaded worker model.
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><!DOCTYPE html><html><head>' . $tagHtml . '</head></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        $head = $document->getElementsByTagName('head')->item(0);
        if ($head === null) {
            return null;
        }

        foreach ($head->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            $attributes = [];
            foreach ($childNode->attributes as $attribute) {
                $attributes[strtolower($attribute->name)] = $attribute->value;
            }

            return new ParsedHeadTag(strtolower($childNode->tagName), $attributes, $childNode->textContent);
        }

        return null;
    }
}
