<?php
/**
 * Section schema — the fields each homepage/CMS section type has. Single source of truth shared by
 * the page editor UI and the server-side save, so they can never drift. Mirrors the 9 renderers in
 * includes/sections/.
 *
 * Field tuple: [name, label, type]. type: text|textarea|richtext|number|image.
 * A type may have ONE repeatable group ('repeat') for its list of cards/counters/FAQ items.
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

function section_schema(): array
{
    return [
        'hero' => ['label' => 'Hero banner', 'fields' => [
            ['heading', 'Heading', 'text'], ['subheading', 'Sub-heading', 'textarea'],
            ['cta_label', 'Primary button label', 'text'], ['cta_url', 'Primary button link', 'text'],
            ['secondary_cta_label', 'Secondary button label', 'text'], ['secondary_cta_url', 'Secondary button link', 'text'],
            ['image', 'Background image', 'image'],
        ]],
        'rich_text' => ['label' => 'Text block', 'fields' => [
            ['heading', 'Heading', 'text'], ['body_html', 'Content', 'richtext'],
        ]],
        'features' => ['label' => 'Feature cards', 'fields' => [
            ['heading', 'Heading', 'text'], ['subheading', 'Sub-heading', 'textarea'],
        ], 'repeat' => ['name' => 'cards', 'label' => 'Cards', 'fields' => [
            ['title', 'Title', 'text'], ['text', 'Description', 'textarea'], ['icon', 'Icon name', 'text'],
        ]]],
        'counters' => ['label' => 'Impact counters', 'fields' => [
            ['heading', 'Heading', 'text'],
        ], 'repeat' => ['name' => 'items', 'label' => 'Counters', 'fields' => [
            ['value', 'Number', 'number'], ['suffix', 'Suffix (e.g. +)', 'text'], ['label', 'Label', 'text'],
        ]]],
        'cta_banner' => ['label' => 'Call to action', 'fields' => [
            ['heading', 'Heading', 'text'], ['text', 'Text', 'textarea'],
            ['cta_label', 'Button label', 'text'], ['cta_url', 'Button link', 'text'],
        ]],
        'faq' => ['label' => 'FAQ accordion', 'fields' => [
            ['heading', 'Heading', 'text'],
        ], 'repeat' => ['name' => 'items', 'label' => 'Questions', 'fields' => [
            ['q', 'Question', 'text'], ['a', 'Answer', 'textarea'],
        ]]],
        'campaign_list' => ['label' => 'Campaign list (live)', 'fields' => [
            ['heading', 'Heading', 'text'], ['limit', 'How many to show', 'number'],
        ]],
        'team_grid' => ['label' => 'Team grid (live)', 'fields' => [
            ['heading', 'Heading', 'text'], ['limit', 'How many to show', 'number'],
        ]],
        'testimonial_slider' => ['label' => 'Testimonials (live)', 'fields' => [
            ['heading', 'Heading', 'text'], ['limit', 'How many to show', 'number'],
        ]],
    ];
}

function section_cast(string $type, mixed $value): mixed
{
    return match ($type) {
        'number' => (int) $value,
        'richtext' => clean_html((string) ($value ?? '')),
        default => trim((string) ($value ?? '')),
    };
}

/** Rebuild a clean settings array for one section from raw posted values. Schema is authoritative. */
function section_build_settings(string $type, array $raw): array
{
    $schema = section_schema()[$type] ?? null;
    if ($schema === null) {
        return [];
    }
    $out = [];
    foreach ($schema['fields'] as [$name, , $ftype]) {
        $out[$name] = section_cast($ftype, $raw[$name] ?? null);
    }
    if (isset($schema['repeat'])) {
        $rep = $schema['repeat'];
        $items = [];
        foreach (is_array($raw[$rep['name']] ?? null) ? $raw[$rep['name']] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $built = [];
            $allEmpty = true;
            foreach ($rep['fields'] as [$fn, , $ft]) {
                $v = section_cast($ft, $row[$fn] ?? null);
                $built[$fn] = $v;
                if ($v !== '' && $v !== 0) {
                    $allEmpty = false;
                }
            }
            if (!$allEmpty) {
                $items[] = $built;
            }
        }
        $out[$rep['name']] = $items;
    }
    return $out;
}
