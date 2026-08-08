<?php
/**
 * =============================================================================
 *  Lightweight i18n scaffold (initially English).
 * =============================================================================
 *  - Languages live in /lang/<code>.php returning an associative array.
 *  - The active language is remembered in a cookie (?lang=xx to switch).
 *  - t('key', 'Default text') returns the translation or the default.
 *  Retrofit strings gradually by wrapping visible text in t(); untranslated
 *  strings simply fall back to the default you pass.
 * =============================================================================
 */

declare(strict_types=1);

/** Languages the site ships with (code => [label, dir]). */
function available_languages(): array
{
    return [
        'en' => ['label' => 'English', 'dir' => 'ltr'],
        'hi' => ['label' => 'हिन्दी',   'dir' => 'ltr'],
    ];
}

/** The current language code. */
function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $available = available_languages();
    if (isset($_GET['lang']) && isset($available[$_GET['lang']])) {
        $lang = $_GET['lang'];
        setcookie('pwf_lang', $lang, time() + 31536000, '/');
    } elseif (isset($_COOKIE['pwf_lang']) && isset($available[$_COOKIE['pwf_lang']])) {
        $lang = $_COOKIE['pwf_lang'];
    } else {
        $lang = 'en';
    }
    return $lang;
}

/** Text direction for the current language. */
function current_dir(): string
{
    return available_languages()[current_lang()]['dir'] ?? 'ltr';
}

/** Load + cache the active translation table. */
function lang_table(): array
{
    static $table = null;
    if ($table === null) {
        $file = BASE_PATH . '/lang/' . current_lang() . '.php';
        $table = is_file($file) ? (require $file) : [];
        if (!is_array($table)) {
            $table = [];
        }
    }
    return $table;
}

/** Translate a key; falls back to $default (or the key). */
function t(string $key, ?string $default = null): string
{
    $table = lang_table();
    return $table[$key] ?? ($default ?? $key);
}
