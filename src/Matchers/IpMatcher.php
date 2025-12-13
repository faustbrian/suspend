<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Suspend\Matchers;

use Cline\Suspend\Matchers\Contracts\Matcher;

use const FILTER_VALIDATE_IP;

use function count;
use function explode;
use function filter_var;
use function inet_pton;
use function ip2long;
use function is_numeric;
use function is_object;
use function is_scalar;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function mb_trim;
use function method_exists;
use function ord;
use function str_contains;

/**
 * Matcher for IP addresses.
 *
 * Supports IPv4 and IPv6 addresses with CIDR notation:
 * - `192.168.1.1` - exact match
 * - `192.168.1.0/24` - CIDR range match
 * - `10.0.0.0/8` - Class A network match
 * - `2001:db8::/32` - IPv6 CIDR match
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class IpMatcher implements Matcher
{
    /**
     * Get the matcher type identifier.
     *
     * @return string The type identifier 'ip'
     */
    public function type(): string
    {
        return 'ip';
    }

    /**
     * Normalize a value into a consistent string format.
     *
     * Converts various input types (strings, scalars, objects with __toString)
     * into a trimmed string representation. Non-convertible values return empty string.
     *
     * @param  mixed  $value The value to normalize (string, scalar, or object with __toString)
     * @return string The normalized string value, or empty string if not convertible
     */
    public function normalize(mixed $value): string
    {
        if (is_string($value)) {
            return mb_trim($value);
        }

        if (is_scalar($value)) {
            return mb_trim((string) $value);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return mb_trim((string) $value);
        }

        return '';
    }

    /**
     * Check if an IP address matches the suspended value.
     *
     * Supports both exact IP matching and CIDR range matching. When the suspended
     * value contains a forward slash, it's treated as CIDR notation and range matching
     * is performed. Otherwise, exact string comparison is used.
     *
     * @param  string $suspendedValue The IP or CIDR range stored in the suspension record
     * @param  mixed  $checkValue     The IP address to check (will be normalized)
     * @return bool   True if the IP matches exactly or falls within the CIDR range, false otherwise
     */
    public function matches(string $suspendedValue, mixed $checkValue): bool
    {
        $checkIp = $this->normalize($checkValue);
        $suspended = $this->normalize($suspendedValue);

        if ($suspended === $checkIp) {
            return true;
        }

        if (str_contains($suspended, '/')) {
            return $this->matchesCidr($suspended, $checkIp);
        }

        return false;
    }

    /**
     * Validate that a value is an acceptable IP address or CIDR range.
     *
     * Accepts both plain IP addresses (IPv4 and IPv6) and CIDR notation ranges.
     * Uses PHP's filter_var with FILTER_VALIDATE_IP for IP validation.
     *
     * @param  mixed $value The value to validate as an IP address or CIDR range
     * @return bool  True if the value is a valid IP address or CIDR range, false otherwise
     */
    public function validate(mixed $value): bool
    {
        $normalized = $this->normalize($value);

        if (str_contains($normalized, '/')) {
            return $this->validateCidr($normalized);
        }

        return filter_var($normalized, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Extract meaningful information from an IP address.
     *
     * IP addresses are atomic values without extractable components,
     * so this method always returns null.
     *
     * @param mixed $value The IP address value
     */
    public function extract(mixed $value): ?string
    {
        return null;
    }

    /**
     * Check if an IP address matches a CIDR range.
     *
     * Parses the CIDR notation and delegates to the appropriate IPv4 or IPv6
     * matching logic based on whether the subnet contains a colon.
     *
     * @param  string $cidr The CIDR notation range (e.g., '192.168.1.0/24' or '2001:db8::/32')
     * @param  string $ip   The IP address to check against the range
     * @return bool   True if the IP falls within the CIDR range, false otherwise
     */
    private function matchesCidr(string $cidr, string $ip): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $bits = (int) $bits;

        $isIpv6 = str_contains($subnet, ':');

        if ($isIpv6) {
            return $this->matchesIpv6Cidr($subnet, $bits, $ip);
        }

        return $this->matchesIpv4Cidr($subnet, $bits, $ip);
    }

    /**
     * Check if an IPv4 address matches a CIDR range.
     *
     * Uses bitwise operations to compare the network portions of both addresses.
     * Converts IPs to 32-bit integers and applies a subnet mask to determine
     * if they belong to the same network.
     *
     * @param  string $subnet The subnet base address (e.g., '192.168.1.0')
     * @param  int    $bits   The number of network bits in the mask (0-32)
     * @param  string $ip     The IP address to check
     * @return bool   True if the IP is in the subnet, false otherwise
     */
    private function matchesIpv4Cidr(string $subnet, int $bits, string $ip): bool
    {
        $subnetLong = ip2long($subnet);
        $ipLong = ip2long($ip);

        if ($subnetLong === false || $ipLong === false) {
            return false;
        }

        $mask = -1 << (32 - $bits);

        return ($subnetLong & $mask) === ($ipLong & $mask);
    }

    /**
     * Check if an IPv6 address matches a CIDR range.
     *
     * Compares the binary representations of the subnet and IP addresses
     * byte-by-byte, then checks any remaining bits with a calculated mask.
     * Uses 8-bit encoding for binary string operations to handle non-UTF-8 data.
     *
     * @param  string $subnet The subnet base address (e.g., '2001:db8::')
     * @param  int    $bits   The number of network bits in the mask (0-128)
     * @param  string $ip     The IPv6 address to check
     * @return bool   True if the IP is in the subnet, false otherwise
     */
    private function matchesIpv6Cidr(string $subnet, int $bits, string $ip): bool
    {
        $subnetBin = inet_pton($subnet);
        $ipBin = inet_pton($ip);

        if ($subnetBin === false || $ipBin === false) {
            return false;
        }

        $fullBytes = (int) ($bits / 8);
        $remainingBits = $bits % 8;

        if (mb_substr($subnetBin, 0, $fullBytes, '8bit') !== mb_substr($ipBin, 0, $fullBytes, '8bit')) {
            return false;
        }

        if ($remainingBits > 0 && mb_strlen($subnetBin, '8bit') > $fullBytes) {
            $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
            $subnetByte = ord($subnetBin[$fullBytes][0][0][0][0]);
            $ipByte = ord($ipBin[$fullBytes][0][0][0][0]);

            if (($subnetByte & $mask) !== ($ipByte & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate CIDR notation format and constraints.
     *
     * Ensures the CIDR string contains exactly one forward slash, a valid IP subnet,
     * and a numeric bit count within the valid range for the IP version (0-32 for IPv4,
     * 0-128 for IPv6).
     *
     * @param  string $cidr The CIDR notation string to validate
     * @return bool   True if the CIDR notation is valid, false otherwise
     */
    private function validateCidr(string $cidr): bool
    {
        $parts = explode('/', $cidr);

        if (count($parts) !== 2) {
            return false;
        }

        [$subnet, $bits] = $parts;

        if (!is_numeric($bits)) {
            return false;
        }

        $bits = (int) $bits;

        if (filter_var($subnet, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $isIpv6 = str_contains($subnet, ':');

        if ($isIpv6) {
            return $bits >= 0 && $bits <= 128;
        }

        return $bits >= 0 && $bits <= 32;
    }
}
