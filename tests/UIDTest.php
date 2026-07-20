<?php

/**
 * smolUID: https://github.com/joby-lol/smol-uid
 * (c) 2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\UID;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UIDTest extends TestCase
{
    public function test_version_0()
    {
        $uid = UID::generate(UID::VERSION_0);
        $this->assertEquals(UID::VERSION_0, $uid->version());
        $this->assertSame($uid, UID::fromString((string) $uid));
        $this->assertSame($uid, UID::fromInt($uid->value));
        $this->assertEquals(0, $uid->time());
        $this->assertEquals(59, $uid->entropyBits());
        $this->assertEquals($uid->value >> 4 & ((1 << 59) - 1), $uid->random());
    }

    public function test_version_1_0()
    {
        $uid = UID::generate(UID::VERSION_1_0);
        $this->assertEquals(UID::VERSION_1_0, $uid->version());
        $this->assertSame($uid, UID::fromString((string) $uid));
        $this->assertSame($uid, UID::fromInt($uid->value));
        $this->assertGreaterThanOrEqual(time(), $uid->time());
        $this->assertEquals(14, $uid->entropyBits());
        $this->assertEquals($uid->value >> 4 & ((1 << 14) - 1), $uid->random());
    }

    public function test_version_1_1()
    {
        $uid = UID::generate(UID::VERSION_1_1);
        $this->assertEquals(UID::VERSION_1_1, $uid->version());
        $this->assertSame($uid, UID::fromString((string) $uid));
        $this->assertSame($uid, UID::fromInt($uid->value));
        $this->assertGreaterThanOrEqual(intdiv(time(), 256), $uid->time());
        $this->assertEquals(22, $uid->entropyBits());
        $this->assertEquals($uid->value >> 4 & ((1 << 22) - 1), $uid->random());
    }

    public function test_version_1_2()
    {
        $uid = UID::generate(UID::VERSION_1_2);
        $this->assertEquals(UID::VERSION_1_2, $uid->version());
        $this->assertSame($uid, UID::fromString((string) $uid));
        $this->assertSame($uid, UID::fromInt($uid->value));
        $this->assertGreaterThanOrEqual(intdiv(time(), 65536), $uid->time());
        $this->assertEquals(30, $uid->entropyBits());
        $this->assertEquals($uid->value >> 4 & ((1 << 30) - 1), $uid->random());
    }

    public function test_version_1_3()
    {
        $uid = UID::generate(UID::VERSION_1_3);
        $this->assertEquals(UID::VERSION_1_3, $uid->version());
        $this->assertSame($uid, UID::fromString((string) $uid));
        $this->assertSame($uid, UID::fromInt($uid->value));
        $this->assertGreaterThanOrEqual(intdiv(time(), 262144), $uid->time());
        $this->assertEquals(32, $uid->entropyBits());
        $this->assertEquals($uid->value >> 4 & ((1 << 32) - 1), $uid->random());
    }

    public function test_version_1_4()
    {
        $uid = UID::generate(UID::VERSION_1_4);
        $this->assertEquals(UID::VERSION_1_4, $uid->version());
        $this->assertSame($uid, UID::fromString((string) $uid));
        $this->assertSame($uid, UID::fromInt($uid->value));
        $this->assertGreaterThanOrEqual(intdiv(time(), 1048576), $uid->time());
        $this->assertEquals(34, $uid->entropyBits());
        $this->assertEquals($uid->value >> 4 & ((1 << 34) - 1), $uid->random());
    }

    public function test_hmac_generation()
    {
        $uid = UID::hmacGenerate('foo', 'bar');
        $this->assertEquals(UID::VERSION_0, $uid->version());
        $this->assertEquals(4980502661450870528, $uid->value);
        $this->assertEquals('11u80ugb7uj28', (string)$uid);
    }

    public function test_hash_generation()
    {
        $uid = UID::hashGenerate('foo');
        $this->assertEquals(UID::VERSION_0, $uid->version());
        $this->assertEquals(5407043158477369760, $uid->value);
        $this->assertEquals('152vwtziw5eow', (string) $uid);
    }

    public function test_invalid_version_generation()
    {
        $this->expectException(InvalidArgumentException::class);
        UID::generate(99);
    }

    public function test_invalid_version_from_integer()
    {
        // NOTE: if versions all the way through 15 are supported in the future, this test will need to be updated/removed
        $this->expectException(InvalidArgumentException::class);
        UID::fromInt(15);
    }

    public function test_constructor_with_negative_integer()
    {
        $this->expectException(InvalidArgumentException::class);
        UID::fromInt(-1);
    }

    public function test_json_serialization()
    {
        $uid = UID::generate(UID::VERSION_1_0);
        $this->assertEquals('"' . (string)$uid . '"', json_encode($uid));
    }

    /**
     * Verify that identity mapping ensures the same integer always results in 
     * the exact same object instance (strict equality).
     */
    public function test_identity_mapping()
    {
        $int = 1234567890;
        $uid1 = UID::fromInt($int);
        $uid2 = UID::fromInt($int);
        $uid3 = UID::fromString((string) $uid1);

        // Test for strict instance equality
        $this->assertSame($uid1, $uid2, 'Objects from identical integers must be the same instance.');
        $this->assertSame($uid1, $uid3, 'Objects reconstituted from strings must be the same instance.');

        // Verify generators route through the identity map
        $gen1 = UID::hashGenerate('identity-test');
        $gen2 = UID::hashGenerate('identity-test');
        $this->assertSame($gen1, $gen2, 'Deterministic generators must respect the identity map.');
    }

    /**
     * Ensure that the garbage collector does not remove objects that 
     * are still in use elsewhere in the application.
     */
    public function test_garbage_collector_persists_active_objects()
    {
        $uid = UID::fromInt(77777);

        UID::garbageCollect();

        $ref = new \ReflectionClass(UID::class);
        $cache = $ref->getStaticPropertyValue('cache');

        $this->assertArrayHasKey((UID::class) . ':77777', $cache, 'Active UID must not be removed from cache.');
        $this->assertSame($uid, $cache[(UID::class) . ':77777']->get(), 'Cached reference must still point to the active object.');
    }

    /**
     * Verify that static factory methods return instances of the calling class
     * when UID is extended (late static binding).
     */
    public function test_subclass_generate_returns_child_class()
    {
        $uid = TypedUID::generate(UID::VERSION_1_0);
        $this->assertInstanceOf(TypedUID::class, $uid);
    }

    public function test_subclass_from_int_returns_child_class()
    {
        $uid = TypedUID::fromInt(1234567890);
        $this->assertInstanceOf(TypedUID::class, $uid);
    }

    public function test_subclass_from_string_returns_child_class()
    {
        $str = (string) TypedUID::fromInt(1234567890);
        $uid = TypedUID::fromString($str);
        $this->assertInstanceOf(TypedUID::class, $uid);
    }

    public function test_subclass_hash_generate_returns_child_class()
    {
        $uid = TypedUID::hashGenerate('foo');
        $this->assertInstanceOf(TypedUID::class, $uid);
    }

    public function test_subclass_hmac_generate_returns_child_class()
    {
        $uid = TypedUID::hmacGenerate('foo', 'bar');
        $this->assertInstanceOf(TypedUID::class, $uid);
    }

    /**
     * Identity map must be per-class — a TypedUID and a UID with the same
     * integer value are different things and must not alias each other.
     */
    public function test_subclass_identity_map_is_isolated_from_parent()
    {
        $parent = UID::fromInt(1234567890);
        $child = TypedUID::fromInt(1234567890);

        $this->assertInstanceOf(UID::class, $parent);
        $this->assertInstanceOf(TypedUID::class, $child);
        $this->assertNotSame($parent, $child);
    }
}
