<?php

namespace App\Actions;

use InvalidArgumentException;

/**
 * Read a legal document out of config with its placeholders filled in.
 *
 * The single point where `config/legal-documents.php` (the wording) meets
 * `config/legal.php` (the details it is written around), so the web pages and
 * the mobile app cannot end up rendering different versions of the same
 * document — which is the failure this whole arrangement exists to prevent.
 */
class ResolveLegalDocument
{
    /**
     * The documents that can be asked for, in the order they should be read.
     *
     * @var list<string>
     */
    public const KEYS = ['terms', 'privacy'];

    /**
     * Resolve one document.
     *
     * @return array{key: string, title: string, updated_at: string, sections: list<array<string, mixed>>}
     */
    public function handle(string $key): array
    {
        if (! in_array($key, self::KEYS, true)) {
            throw new InvalidArgumentException("Unknown legal document [{$key}].");
        }

        $document = config("legal-documents.{$key}");

        return [
            'key' => $key,
            'title' => $document['title'],
            'updated_at' => config("legal.{$key}_updated_at"),
            'sections' => array_map(
                fn (array $section): array => $this->fill($section),
                $document['sections'],
            ),
        ];
    }

    /**
     * Both documents, in reading order — what the app draws on one screen.
     *
     * @return list<array{key: string, title: string, updated_at: string, sections: list<array<string, mixed>>}>
     */
    public function all(): array
    {
        return array_map(fn (string $key): array => $this->handle($key), self::KEYS);
    }

    /**
     * Substitute the placeholders through one section.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function fill(array $section): array
    {
        $replacements = [
            ':app' => config('app.name'),
            ':company' => config('legal.company'),
            ':email' => config('legal.contact_email'),
            ':jurisdiction' => config('legal.jurisdiction'),
            ':age' => (string) config('legal.minimum_age'),
            ':backup_days' => (string) config('legal.backup_retention_days'),
        ];

        $apply = fn (string $text): string => strtr($text, $replacements);

        return [
            'heading' => $apply($section['heading']),
            'blocks' => array_map(function (array $block) use ($apply): array {
                // `text` is a string on a paragraph and a list of them inside a
                // callout; `items` carries a list's own lines. Whichever the
                // block has gets the same substitution.
                if (isset($block['items'])) {
                    return [...$block, 'items' => array_map($apply, $block['items'])];
                }

                return [
                    ...$block,
                    'text' => is_array($block['text'])
                        ? array_map($apply, $block['text'])
                        : $apply($block['text']),
                ];
            }, $section['blocks']),
        ];
    }
}
