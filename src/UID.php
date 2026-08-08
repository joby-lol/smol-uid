<?php

/**
 * smolUID: https://github.com/joby-lol/smol-uid
 * (c) 2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\UID;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;
use WeakReference;

/**
 * Lightweight time-ordered ID class that is an integer under the hood but is stringable for more human-readable output. String representations will be 10 characters long for years to come, but will grow as the ID integers increase in size. The IDs are sortable by generation time, in both integer and string forms, and the resolution of that sorting is adjustable by picking versions that trim different numbers of bits from the timestamp in favor of more random bits.
 * 
 * @phpstan-consistent-constructor
 */
class UID implements Stringable, JsonSerializable
{

    /** @var array<string,WeakReference<static>> $cache cache of weak references */
    protected static array $cache = [];

    /**
     * Version 0, reserved for entirely random IDs with no time information in them at all. Random IDs are somewhat different, in that they will always occupy the full 63 bits, their most significant bit will always be one (to stabilize their string length), leaving 58 entirely random bits.
     */
    public const VERSION_0 = 0;

    /**
     * Version 1.0, with full second resolution timestamp and 14 random bits.
     */
    public const VERSION_1_0 = 1;

    /**
     * Version 1.1, trims 8 bits for a resolution of about 4.25 minutes, with 22 random bits.
     */
    public const VERSION_1_1 = 2;

    /**
     * Version 1.2, trims 16 bits for a resolution of about 18 hours, with 30 random bits.
     */
    public const VERSION_1_2 = 3;

    /**
     * Version 1.3, trims 18 bits for a resolution of about 3 days, with 32 random bits.
     */
    public const VERSION_1_3 = 4;

    /**
     * Version 1.4, trims 20 bits for a resolution of about 12 days, with 34 random bits.
     */
    public const VERSION_1_4 = 5;

    /**
     * Configuration for each supported version of UID, mapping version numbers to arrays of [dropped bits, entropy bits].
     */
    public const VERSION_CONFIGS = [
        0 => [null, 59],
        1 => [0, 14],
        2 => [8, 22],
        3 => [16, 30],
        4 => [18, 32],
        5 => [20, 34],
    ];

    /**
     * @var int<0,max> The integer ID value. Should be a positive integer, and as of 2025 all defined versions fit in 63 bits for ease of use and compatibility.
     */
    public readonly int $value;

    /**
     * Create a UID object from its string representation.
     * 
     * @throws InvalidArgumentException if the string is not a valid UID.
     */
    public static function fromString(string $uid): static
    {
        $int = base_convert(strtolower($uid), 36, 10);
        return static::fromInt(intval($int));
    }

    /**
     * Create a UID object from its integer representation.
     * 
     * @throws InvalidArgumentException if the integer is negative.
     */
    public static function fromInt(int $uid): static
    {
        // check for existing object in weak map
        $cache_id = static::class . ':' . $uid;
        if (array_key_exists($cache_id, static::$cache)) {
            $object = static::$cache[$cache_id]->get();
            if ($object !== null) {
                return $object;
            }
        }
        // create new object and store weak reference
        $object = new static($uid);
        static::$cache[$cache_id] = WeakReference::create($object);
        return $object;
    }

    public static function garbageCollect(): void
    {
        foreach (static::$cache as $key => $ref) {
            if ($ref->get() === null) {
                unset(static::$cache[$key]);
            }
        }
    }

    /**
     * Generate a new UID of the specified version.
     * 
     * @throws InvalidArgumentException if the version is unsupported.
     */
    public static function generate(int $version = self::VERSION_0): static
    {
        // special case for fully-random ones
        if ($version == self::VERSION_0) {
            $int = random_int(0, (1 << 58) - 1) << 4;
            $int = $int | self::VERSION_0;
            $int = $int | (1 << 62);
            return static::fromInt($int);
        }
        // normal generation
        if (!array_key_exists($version, self::VERSION_CONFIGS)) {
            throw new InvalidArgumentException('Unsupported UID version');
        }
        $droppedBits = self::VERSION_CONFIGS[$version][0];
        $entropyBits = self::VERSION_CONFIGS[$version][1];
        // start with the time portion
        $int = time();
        $int = $int >> $droppedBits;
        // shift to make room for entropy and add it
        $int = $int << $entropyBits;
        $int = $int | random_int(0, (1 << $entropyBits) - 1);
        // add version in the lowest 4 bits
        $int = $int << 4;
        $int = $int | $version;
        // return finished UID
        return static::fromInt($int);
    }

    /**
     * Generate a UID deterministically from a source string and secret, using HMAC to produce a pseudo-random but deterministic value.
     */
    public static function hmacGenerate(string $source, string $secret): static
    {
        // hmac to get a deterministic but unpredictable hash and truncate to 64 bits
        $hash = hash_hmac('sha256', $source, $secret);
        $int = (int) base_convert(substr($hash, 0, 16), 16, 10);
        // shift 64-bit value right 4 to get 58 random bits
        $int = $int >> 6;
        // 4 lsb are 0000 for version 0
        $int = $int << 4;
        // make 63rd bit 1
        $int = $int | 1 << 62;
        // return finished UID
        return static::fromInt($int);
    }

    /**
     * Generate a UID deterministically from a source string, using a hash to produce a pseudo-random but deterministic value. Useful when you need deterministic UIDs but the security of a full HMAC hash is not required.
     */
    public static function hashGenerate(string $source): static
    {
        // hmac to get a deterministic but unpredictable hash and truncate to 64 bits
        $hash = hash('sha256', $source);
        $int = (int) base_convert(substr($hash, 0, 16), 16, 10);
        // shift 64-bit value right 4 to get 58 random bits
        $int = $int >> 6;
        // 4 lsb are 0000 for version 0
        $int = $int << 4;
        // make 63rd bit 1
        $int = $int | 1 << 62;
        // return finished UID
        return static::fromInt($int);
    }

    /**
     * Construct a UID object from an integer representation.
     * 
     * @throws InvalidArgumentException if the integer is not a valid UID
     */
    protected function __construct(int $id)
    {
        if ($id < 0) {
            throw new InvalidArgumentException('UID integer must not be negative');
        }
        $this->value = $id;
        if (!array_key_exists($this->version(), self::VERSION_CONFIGS)) {
            throw new InvalidArgumentException('Unsupported UID version');
        }
    }

    /**
     * Get the version number, which is the lowest 4 bits.
     */
    public function version(): int
    {
        return $this->value & 0x0F;
    }

    /**
     * Return just the timestamp portion of the UID, at the low end of the window of the time the UID was generated in.
     */
    public function time(): int
    {
        $version = $this->version();
        if ($version === self::VERSION_0) {
            return 0;
        }
        assert(array_key_exists($version, self::VERSION_CONFIGS));
        $droppedBits = self::VERSION_CONFIGS[$version][0];
        $entropyBits = self::VERSION_CONFIGS[$version][1];
        $int = $this->value >> 4; // remove version bits
        $int = $int >> $entropyBits; // remove entropy bits
        $int = $int << $droppedBits; // shift back to original position
        return $int;
    }

    /**
     * Return the random portion of the UID, which is the low bits after the timestamp but before the version.
     */
    public function random(): int
    {
        $version = $this->version();
        if ($version === self::VERSION_0) {
            return $this->value >> 4; // remove version bits
        }
        assert(array_key_exists($version, self::VERSION_CONFIGS));
        $entropyBits = self::VERSION_CONFIGS[$version][1];
        $int = $this->value >> 4; // remove version bits
        $int = $int & ((1 << $entropyBits) - 1); // mask to get only the random bits
        return $int;
    }

    /**
     * Get the number of random bits for this UID version.
     */
    public function entropyBits(): int
    {
        return self::VERSION_CONFIGS[$this->version()][1];
    }

    /**
     * Get the string representation of the UID, which is a base36 encoding of the integer value.
     */
    public function __toString(): string
    {
        return base_convert((string) $this->value, 10, 36);
    }

    /**
     * UIDs serialize to JSON as their string representation.
     */
    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

}
