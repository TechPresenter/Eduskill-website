<?php
/**
 * =============================================================================
 *  Country registry — ISO code, name, dial code and phone-length rules
 * =============================================================================
 *  Backs the country selector used on every public form. Self-contained: no
 *  Composer package and no CDN, so it works offline and inside the site CSP.
 *
 *  Each row is [dial code, min national digits, max national digits]. The
 *  lengths are the *national significant number* (what the visitor types after
 *  the dial code). Where a country has many overlapping formats the range is
 *  deliberately permissive — E.164 caps the whole number at 15 digits, so the
 *  widest sane range is 4..15. Validation is a sanity check to catch typos, not
 *  a carrier-grade numbering-plan implementation.
 * =============================================================================
 */

declare(strict_types=1);

/** ISO-3166-1 alpha-2 => [name, dial, minLen, maxLen] */
function countries_all(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = [
        'AF' => ['Afghanistan', '93', 9, 9],        'AL' => ['Albania', '355', 8, 9],
        'DZ' => ['Algeria', '213', 8, 9],           'AD' => ['Andorra', '376', 6, 9],
        'AO' => ['Angola', '244', 9, 9],            'AG' => ['Antigua & Barbuda', '1268', 7, 7],
        'AR' => ['Argentina', '54', 10, 11],        'AM' => ['Armenia', '374', 8, 8],
        'AW' => ['Aruba', '297', 7, 7],             'AU' => ['Australia', '61', 9, 9],
        'AT' => ['Austria', '43', 7, 13],           'AZ' => ['Azerbaijan', '994', 9, 9],
        'BS' => ['Bahamas', '1242', 7, 7],          'BH' => ['Bahrain', '973', 8, 8],
        'BD' => ['Bangladesh', '880', 10, 10],      'BB' => ['Barbados', '1246', 7, 7],
        'BY' => ['Belarus', '375', 9, 9],           'BE' => ['Belgium', '32', 8, 9],
        'BZ' => ['Belize', '501', 7, 7],            'BJ' => ['Benin', '229', 8, 8],
        'BM' => ['Bermuda', '1441', 7, 7],          'BT' => ['Bhutan', '975', 7, 8],
        'BO' => ['Bolivia', '591', 8, 8],           'BA' => ['Bosnia & Herzegovina', '387', 8, 8],
        'BW' => ['Botswana', '267', 7, 8],          'BR' => ['Brazil', '55', 10, 11],
        'BN' => ['Brunei', '673', 7, 7],            'BG' => ['Bulgaria', '359', 8, 9],
        'BF' => ['Burkina Faso', '226', 8, 8],      'BI' => ['Burundi', '257', 8, 8],
        'KH' => ['Cambodia', '855', 8, 9],          'CM' => ['Cameroon', '237', 9, 9],
        'CA' => ['Canada', '1', 10, 10],            'CV' => ['Cape Verde', '238', 7, 7],
        'KY' => ['Cayman Islands', '1345', 7, 7],   'CF' => ['Central African Rep.', '236', 8, 8],
        'TD' => ['Chad', '235', 8, 8],              'CL' => ['Chile', '56', 9, 9],
        'CN' => ['China', '86', 11, 11],            'CO' => ['Colombia', '57', 10, 10],
        'KM' => ['Comoros', '269', 7, 7],           'CG' => ['Congo', '242', 9, 9],
        'CD' => ['Congo (DRC)', '243', 9, 9],       'CR' => ['Costa Rica', '506', 8, 8],
        'CI' => ["Côte d'Ivoire", '225', 8, 10],    'HR' => ['Croatia', '385', 8, 9],
        'CU' => ['Cuba', '53', 8, 8],               'CY' => ['Cyprus', '357', 8, 8],
        'CZ' => ['Czechia', '420', 9, 9],           'DK' => ['Denmark', '45', 8, 8],
        'DJ' => ['Djibouti', '253', 8, 8],          'DM' => ['Dominica', '1767', 7, 7],
        'DO' => ['Dominican Republic', '1809', 7, 7], 'EC' => ['Ecuador', '593', 9, 9],
        'EG' => ['Egypt', '20', 10, 10],            'SV' => ['El Salvador', '503', 8, 8],
        'GQ' => ['Equatorial Guinea', '240', 9, 9], 'ER' => ['Eritrea', '291', 7, 7],
        'EE' => ['Estonia', '372', 7, 8],           'SZ' => ['Eswatini', '268', 8, 8],
        'ET' => ['Ethiopia', '251', 9, 9],          'FJ' => ['Fiji', '679', 7, 7],
        'FI' => ['Finland', '358', 9, 10],          'FR' => ['France', '33', 9, 9],
        'GA' => ['Gabon', '241', 7, 8],             'GM' => ['Gambia', '220', 7, 7],
        'GE' => ['Georgia', '995', 9, 9],           'DE' => ['Germany', '49', 10, 11],
        'GH' => ['Ghana', '233', 9, 9],             'GI' => ['Gibraltar', '350', 8, 8],
        'GR' => ['Greece', '30', 10, 10],           'GD' => ['Grenada', '1473', 7, 7],
        'GT' => ['Guatemala', '502', 8, 8],         'GN' => ['Guinea', '224', 9, 9],
        'GY' => ['Guyana', '592', 7, 7],            'HT' => ['Haiti', '509', 8, 8],
        'HN' => ['Honduras', '504', 8, 8],          'HK' => ['Hong Kong', '852', 8, 8],
        'HU' => ['Hungary', '36', 9, 9],            'IS' => ['Iceland', '354', 7, 9],
        'IN' => ['India', '91', 10, 10],            'ID' => ['Indonesia', '62', 9, 12],
        'IR' => ['Iran', '98', 10, 10],             'IQ' => ['Iraq', '964', 10, 10],
        'IE' => ['Ireland', '353', 7, 9],           'IL' => ['Israel', '972', 9, 9],
        'IT' => ['Italy', '39', 9, 11],             'JM' => ['Jamaica', '1876', 7, 7],
        'JP' => ['Japan', '81', 10, 10],            'JO' => ['Jordan', '962', 9, 9],
        'KZ' => ['Kazakhstan', '7', 10, 10],        'KE' => ['Kenya', '254', 9, 9],
        'KI' => ['Kiribati', '686', 5, 8],          'KW' => ['Kuwait', '965', 8, 8],
        'KG' => ['Kyrgyzstan', '996', 9, 9],        'LA' => ['Laos', '856', 8, 10],
        'LV' => ['Latvia', '371', 8, 8],            'LB' => ['Lebanon', '961', 7, 8],
        'LS' => ['Lesotho', '266', 8, 8],           'LR' => ['Liberia', '231', 7, 9],
        'LY' => ['Libya', '218', 9, 9],             'LI' => ['Liechtenstein', '423', 7, 9],
        'LT' => ['Lithuania', '370', 8, 8],         'LU' => ['Luxembourg', '352', 6, 11],
        'MO' => ['Macau', '853', 8, 8],             'MG' => ['Madagascar', '261', 9, 9],
        'MW' => ['Malawi', '265', 7, 9],            'MY' => ['Malaysia', '60', 9, 10],
        'MV' => ['Maldives', '960', 7, 7],          'ML' => ['Mali', '223', 8, 8],
        'MT' => ['Malta', '356', 8, 8],             'MR' => ['Mauritania', '222', 8, 8],
        'MU' => ['Mauritius', '230', 7, 8],         'MX' => ['Mexico', '52', 10, 10],
        'MD' => ['Moldova', '373', 8, 8],           'MC' => ['Monaco', '377', 8, 9],
        'MN' => ['Mongolia', '976', 8, 8],          'ME' => ['Montenegro', '382', 8, 8],
        'MA' => ['Morocco', '212', 9, 9],           'MZ' => ['Mozambique', '258', 9, 9],
        'MM' => ['Myanmar', '95', 8, 10],           'NA' => ['Namibia', '264', 9, 9],
        'NP' => ['Nepal', '977', 10, 10],           'NL' => ['Netherlands', '31', 9, 9],
        'NZ' => ['New Zealand', '64', 8, 10],       'NI' => ['Nicaragua', '505', 8, 8],
        'NE' => ['Niger', '227', 8, 8],             'NG' => ['Nigeria', '234', 10, 10],
        'MK' => ['North Macedonia', '389', 8, 8],   'NO' => ['Norway', '47', 8, 8],
        'OM' => ['Oman', '968', 8, 8],              'PK' => ['Pakistan', '92', 10, 10],
        'PS' => ['Palestine', '970', 9, 9],         'PA' => ['Panama', '507', 7, 8],
        'PG' => ['Papua New Guinea', '675', 8, 8],  'PY' => ['Paraguay', '595', 9, 9],
        'PE' => ['Peru', '51', 9, 9],               'PH' => ['Philippines', '63', 10, 10],
        'PL' => ['Poland', '48', 9, 9],             'PT' => ['Portugal', '351', 9, 9],
        'PR' => ['Puerto Rico', '1787', 7, 7],      'QA' => ['Qatar', '974', 8, 8],
        'RO' => ['Romania', '40', 9, 9],            'RU' => ['Russia', '7', 10, 10],
        'RW' => ['Rwanda', '250', 9, 9],            'WS' => ['Samoa', '685', 5, 7],
        'SA' => ['Saudi Arabia', '966', 9, 9],      'SN' => ['Senegal', '221', 9, 9],
        'RS' => ['Serbia', '381', 8, 9],            'SC' => ['Seychelles', '248', 7, 7],
        'SL' => ['Sierra Leone', '232', 8, 8],      'SG' => ['Singapore', '65', 8, 8],
        'SK' => ['Slovakia', '421', 9, 9],          'SI' => ['Slovenia', '386', 8, 8],
        'SB' => ['Solomon Islands', '677', 5, 7],   'SO' => ['Somalia', '252', 7, 9],
        'ZA' => ['South Africa', '27', 9, 9],       'KR' => ['South Korea', '82', 9, 10],
        'SS' => ['South Sudan', '211', 9, 9],       'ES' => ['Spain', '34', 9, 9],
        'LK' => ['Sri Lanka', '94', 9, 9],          'SD' => ['Sudan', '249', 9, 9],
        'SR' => ['Suriname', '597', 6, 7],          'SE' => ['Sweden', '46', 7, 13],
        'CH' => ['Switzerland', '41', 9, 9],        'SY' => ['Syria', '963', 9, 9],
        'TW' => ['Taiwan', '886', 9, 9],            'TJ' => ['Tajikistan', '992', 9, 9],
        'TZ' => ['Tanzania', '255', 9, 9],          'TH' => ['Thailand', '66', 9, 9],
        'TL' => ['Timor-Leste', '670', 7, 8],       'TG' => ['Togo', '228', 8, 8],
        'TO' => ['Tonga', '676', 5, 7],             'TT' => ['Trinidad & Tobago', '1868', 7, 7],
        'TN' => ['Tunisia', '216', 8, 8],           'TR' => ['Türkiye', '90', 10, 10],
        'TM' => ['Turkmenistan', '993', 8, 8],      'UG' => ['Uganda', '256', 9, 9],
        'UA' => ['Ukraine', '380', 9, 9],           'AE' => ['United Arab Emirates', '971', 9, 9],
        'GB' => ['United Kingdom', '44', 10, 10],   'US' => ['United States', '1', 10, 10],
        'UY' => ['Uruguay', '598', 8, 8],           'UZ' => ['Uzbekistan', '998', 9, 9],
        'VU' => ['Vanuatu', '678', 5, 7],           'VE' => ['Venezuela', '58', 10, 10],
        'VN' => ['Vietnam', '84', 9, 10],           'YE' => ['Yemen', '967', 9, 9],
        'ZM' => ['Zambia', '260', 9, 9],            'ZW' => ['Zimbabwe', '263', 9, 9],
    ];
    return $c;
}

/** Default country when detection fails (per requirement). */
function country_default(): string
{
    return 'IN';
}

/** Is this a country code we know? */
function country_valid(string $iso): bool
{
    return isset(countries_all()[strtoupper($iso)]);
}

/** ['iso','name','dial','min','max'] for a code, or the default when unknown. */
function country_get(string $iso): array
{
    $all = countries_all();
    $iso = strtoupper($iso);
    if (!isset($all[$iso])) {
        $iso = country_default();
    }
    [$name, $dial, $min, $max] = $all[$iso];
    return ['iso' => $iso, 'name' => $name, 'dial' => $dial, 'min' => $min, 'max' => $max];
}

/**
 * Best-guess country for the current visitor.
 *
 * Reuses the geo cache the analytics module already maintains (`ip_geo`, filled
 * by pv_geo_resolve via ip-api.com) so this adds no new outbound dependency and
 * no extra latency — it is a local table read. Falls back to India.
 */
function country_detect(): string
{
    // An explicit earlier choice always wins.
    if (!empty($_COOKIE['pwf_country']) && country_valid((string) $_COOKIE['pwf_country'])) {
        return strtoupper((string) $_COOKIE['pwf_country']);
    }
    try {
        $ip = function_exists('client_ip') ? client_ip() : '';
        if ($ip !== '' && function_exists('pv_geo_cached')) {
            $geo = pv_geo_cached($ip);
            $cc  = strtoupper((string) ($geo['country_code'] ?? ''));
            if ($cc !== '' && country_valid($cc)) {
                return $cc;
            }
        }
    } catch (Throwable $e) {
        // fall through to the default
    }
    return country_default();
}

/**
 * Server-side validation of a submitted phone + country.
 * Returns ['ok'=>bool, 'error'=>string, 'e164'=>string, 'national'=>string,
 *          'iso'=>string, 'name'=>string, 'dial'=>string].
 */
function country_validate_phone(string $iso, string $phone, bool $required = true): array
{
    $c = country_get($iso);
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    // Visitors often paste the dial code into the number field; strip it once.
    if ($digits !== '' && str_starts_with($digits, $c['dial']) && strlen($digits) > $c['max']) {
        $digits = substr($digits, strlen($c['dial']));
    }
    $digits = ltrim($digits, '0');

    $base = [
        'iso' => $c['iso'], 'name' => $c['name'], 'dial' => $c['dial'],
        'national' => $digits, 'e164' => $digits === '' ? '' : '+' . $c['dial'] . $digits,
    ];

    if ($digits === '') {
        return $base + ['ok' => !$required, 'error' => $required ? 'Please enter a phone number.' : ''];
    }
    $len = strlen($digits);
    if ($len < $c['min'] || $len > $c['max']) {
        $expect = $c['min'] === $c['max']
            ? $c['min'] . ' digits'
            : $c['min'] . '–' . $c['max'] . ' digits';
        return $base + ['ok' => false, 'error' => 'Enter a valid ' . $c['name'] . ' number (' . $expect . ').'];
    }
    return $base + ['ok' => true, 'error' => ''];
}

/** Country list as JSON for the browser component (cached per request). */
function countries_json(): string
{
    static $json = null;
    if ($json === null) {
        $out = [];
        foreach (countries_all() as $iso => [$name, $dial, $min, $max]) {
            $out[] = ['i' => $iso, 'n' => $name, 'd' => $dial, 'mn' => $min, 'mx' => $max];
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    return $json;
}
