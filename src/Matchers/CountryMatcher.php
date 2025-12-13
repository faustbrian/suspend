<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Matchers;

use Cline\Suspend\Matchers\Contracts\Matcher;

use function in_array;
use function is_object;
use function is_scalar;
use function is_string;
use function mb_strlen;
use function mb_strtoupper;
use function mb_trim;
use function method_exists;
use function preg_match;

/**
 * Matcher for ISO 3166-1 alpha-2 country codes.
 *
 * Validates and matches two-letter country codes according to the
 * ISO 3166-1 alpha-2 standard. Useful for geographic access restrictions
 * based on GeoIP resolution. All codes are normalized to uppercase for
 * consistent storage and matching.
 *
 * ```php
 * $matcher = new CountryMatcher();
 * $matcher->matches('US', 'us'); // true (case-insensitive)
 * $matcher->validate('GB'); // true
 * $matcher->validate('USA'); // false (must be 2 letters)
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CountryMatcher implements Matcher
{
    /**
     * Common ISO 3166-1 alpha-2 country codes.
     *
     * Provides validation against known country codes to prevent typos
     * and invalid codes. Not exhaustive but covers all recognized countries
     * and territories as of the ISO 3166-1 alpha-2 standard.
     */
    private const array COMMON_CODES = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS',
        'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
        'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
        'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF',
        'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM',
        'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM',
        'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC',
        'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK',
        'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
        'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG',
        'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
        'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO',
        'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
        'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
    ];

    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'country';
    }

    /**
     * {@inheritDoc}
     */
    public function normalize(mixed $value): string
    {
        if (is_string($value)) {
            return mb_strtoupper(mb_trim($value));
        }

        if (is_scalar($value)) {
            return mb_strtoupper(mb_trim((string) $value));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return mb_strtoupper(mb_trim((string) $value));
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        return $this->normalize($suspendedValue) === $this->normalize($checkValue);
    }

    /**
     * {@inheritDoc}
     */
    public function validate(mixed $value): bool
    {
        $normalized = $this->normalize($value);

        // Must be exactly 2 uppercase letters
        if (mb_strlen($normalized) !== 2) {
            return false;
        }

        if (!preg_match('/^[A-Z]{2}$/', $normalized)) {
            return false;
        }

        // Optionally verify against known codes
        return in_array($normalized, self::COMMON_CODES, true);
    }

    /**
     * {@inheritDoc}
     *
     * Returns null for country codes (no meaningful extraction).
     */
    public function extract(mixed $value): ?string
    {
        return null;
    }
}
