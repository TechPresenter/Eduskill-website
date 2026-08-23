<?php
/**
 * =============================================================================
 *  Schemes — shared parsing for the richer project pages
 * =============================================================================
 *  The schemes module stores its list-shaped content as plain text, one item
 *  per line, because that is what an administrator can actually edit in a
 *  textarea without learning a syntax. These helpers turn those fields back
 *  into arrays, and they live here so admin/schemes.php and schemes.php cannot
 *  disagree about the format.
 *
 *  Formats, all newline-delimited:
 *      objectives / partnership / transparency / process_steps   one per line
 *      support_items       "Label | Amount"      (indicative budget table)
 *      faq                 "Question :: Answer"
 *      brochures           JSON [{label, path, size}]
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Upload folder for scheme brochures.
 *
 * Deliberately NOT uploads/documents: that directory carries a
 * "Require all denied" .htaccess because it holds applicant PII, and anything
 * placed there 403s for the public. Brochures are published downloads, so they
 * belong in a normally-served folder.
 */
const SCHEME_BROCHURE_DIR = 'brochures';

/**
 * Split a one-item-per-line field into a clean array.
 * Mirrors the $toList() closure schemes.php already used for eligibility.
 */
function scheme_list(?string $text): array
{
    $text = trim((string) $text);
    if ($text === '') {
        return [];
    }
    $out = [];
    foreach (preg_split('/\r\n|\r|\n|•|;/u', $text) ?: [] as $p) {
        $p = trim($p, " \t\r\n-–—*·•▪ ");
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

/**
 * The indicative budget table: "Label | Amount" per line.
 *
 * A line without a separator still yields a row (label only), so a half-filled
 * field renders rather than silently disappearing. The last row is flagged as
 * the total when its label looks like one — that is purely presentational.
 */
function scheme_budget_rows(?string $text): array
{
    $rows = [];
    foreach (scheme_list($text) as $line) {
        $parts  = array_map('trim', explode('|', $line, 2));
        $label  = $parts[0] ?? '';
        $amount = $parts[1] ?? '';
        if ($label === '' && $amount === '') {
            continue;
        }
        $rows[] = [
            'label'  => $label,
            'amount' => $amount,
            'total'  => (bool) preg_match('/^(कुल|total|कुल\s|grand total)/iu', $label),
        ];
    }
    return $rows;
}

/** FAQ entries: "Question :: Answer" per line. Lines without :: are skipped. */
function scheme_faq(?string $text): array
{
    $out = [];
    foreach (scheme_list($text) as $line) {
        if (!str_contains($line, '::')) {
            continue;
        }
        [$q, $a] = array_map('trim', explode('::', $line, 2));
        if ($q !== '' && $a !== '') {
            $out[] = ['q' => $q, 'a' => $a];
        }
    }
    return $out;
}

/** Extra downloads attached to a scheme; always an array of well-formed rows. */
function scheme_brochures(?array $row): array
{
    $raw = $row['brochures'] ?? null;
    if (empty($raw)) {
        return [];
    }
    $list = json_decode((string) $raw, true);
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $b) {
        if (!is_array($b) || empty($b['path'])) {
            continue;
        }
        $out[] = [
            'label' => (string) ($b['label'] ?? basename((string) $b['path'])),
            'path'  => (string) $b['path'],
            'size'  => (int) ($b['size'] ?? 0),
        ];
    }
    return $out;
}

/** True when a scheme has anything downloadable at all. */
function scheme_has_downloads(?array $row): bool
{
    return !empty($row['brochure']) || scheme_brochures($row) !== [];
}
