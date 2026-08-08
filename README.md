# smolUID

A simple and lightweight time-ordered random ID library designed for human-scale applications.

## What is smolUID?

smolUID is a simple, lightweight library for generating unique, time-ordered, human-readable IDs. Unlike full UUIDs, more complex enterprise-grade time-ordered IDs, or simple auto-incrementing database IDs, UIDs are:

- **Human-readable**: String representations as base 36 integers for more compact (10-13 characters) format for URLs, markup, etc.
- **Time-ordered**: Can be represented as integers or strings, and both are naturally sortable by rough creation time
- **URL-safe**: No special characters that are hard to type or need encoding in URLs
- **Privacy-conscious**: Can drop optional amounts of timestamp precision to avoid leaking exact creation times
- **Ergonomic**: Strings can be easily converted from the concise format back into the underlying integer, or vice versa
- **Future-proof**: Currently underlying integer values are 63 bits, so they fit in a signed 64-bit integer, but future versions could expand

## Why smolUID?

Not every project needs guaranteed globally unique IDs or distributed systems. For many smaller applications, simpler solutions are often better:

- **Human scale**: Designed for applications where IDs might be seen, shared, or even typed by humans
- **Simplicity**: No external dependencies or complex setup
- **Lightweight**: Minimal overhead and easy integration (it's just an integer!)
- **Chronological**: Natural time-based ordering in both integer and string representations

## Installation

```bash
composer require joby/smol-uid
```

## Basic Usage

### Creating a new UID

```php
use Joby\Smol\UID\UID;

// Generate a new UID
// Default is version 0, which is fully random
// Version 1.1 keeps the full timestamp
// Versions 1.2-1.4 trim increasing amounts of precision from the timestamp
$uid = UID::generate(UID::VERSION_1_1);
```

### UID versions

| Version | Time resolution    | Random bits | String length | Availability     |
| ------- | ------------------ | ----------- | ------------- | ---------------- |
| 0       | N/A (no time data) | 58          | 13            | ~288 quadrillion |
| 1.0     | 1 second           | 14          | 10            | ~1.4 billion/day |
| 1.1     | ~4.25 minutes      | 22          | 10            | ~1.4 billion/day |
| 1.2     | ~18 hours          | 30          | 10            | ~1.4 billion/day |
| 1.3     | ~3 days            | 32          | 10            | ~1.4 billion/day |
| 1.4     | ~12 days           | 34          | 10            | ~1.4 billion/day |

### Getting the string representation

```php
use Joby\Smol\UID\UID;

// Generate a new UID
// in this case a fully random one
$uid = UID::generate();

// Can be used as or cast to a string
// will be a 10-13 character alphanumeric string
$string = (string) $uid;

// can be turned back into a UID object
$sameUID = UID::fromString($string);
```

### Getting the underlying integer

```php
use Joby\Smol\UID\UID;

// Create a UID
$uid = UID::generate();

// Get the underlying integer
$int = $uid->value;

// Convert back to a UID
$sameUID = UID::fromInt($int);
```

### Checking equality

```php
use Joby\Smol\UID\UID;

// Create a UID and a copy from its string
$uid1 = UID::generate();
$uid2 = UID::fromString((string) $uid1);

// They have the same integer value, so == works
$uid1 == $uid2; // true

// The library also ensures they are the same object, so === works too
$uid1 === $uid2; // true
```

## Advanced Usage

### Getting the underlying parts

```php
use Joby\Smol\UID\UID;

$uid = UID::generate();

// Get the version bits
$version = $uid->version();

// Get the approximate timestamp when this UID was created
// This returns the lower bound of when this UID could have been created
$timestamp = $uid->time();

// Get the random bits of the UID
$random = $uid->random();
```

### Using with databases

UIDs can be stored in your database as either strings or integers:

```php
// Store as a string (more readable)
$db->query("INSERT INTO users (id, name) VALUES (?, ?)", [(string)$uid, "John"]);

// Store as an integer (more efficient)
$db->query("INSERT INTO users (id, name) VALUES (?, ?)", [$uid->value, "John"]);
```

### Deterministic generation

UIDs can also be generated deterministically if you want to use them in a manner similar to a hash. In this case they are produced as version 0 UIDs with no time data, and their random data is produced by truncating a sha256 hmac or simple hash of the provided string.

```php
use Joby\Smol\UID\UID;

// derive from a simple SHA256 hash
// faster and easier, but underlying values are less protected
$uid = UID::hashGenerate('some value to generate from');

// derive from an HMAC hash with secret key
// makes guessing underlying values much harder
$uid = UID::hmacGenerate('some value to generate from', 'secret key');
```

### Garbage collection

In long-running scripts that work with large numbers of different UIDs in a single run (think at least hundreds of thousands, if not millions), you may want to garbage-collect the internal cache periodically. This will clear out weak cache references to objects that have been garbage-collected by PHP.

Running garbage collection just takes running `UID::garbageCollect()`

## How It Works

Each UID consists of a single integer value with three parts:

1. 4-bit version identifier in the least significant bits, from which the other two parts' lengths are determined
2. 0 or more bits of random data
3. 0 or more bits of time data in the most significant bits, with varying amounts of precision dropped by truncating least significant bits of the current time

The combination is encoded in base-36 (alphanumeric) when a string representation is required, but can also be stored as an integer. All current versions are at most 63 bits long, allowing them to fit in a normal 64-bit signed integer. This means you can work with their underlying values easily and efficiently in almost any environment, with no special handling.

Future versions may expand to larger bit sizes, but for now the goal is to fit in a single signed integer for maximum compatibility and simplicity.

## Limitations

- Not designed or suitable for distributed systems requiring guaranteed global uniqueness
- Time ordering may be varying levels of approximate due to the dropped precision bits
- No built-in collision detection (though collisions are extremely unlikely at human scale applications)
