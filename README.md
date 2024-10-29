# PHP Cache Class Documentation

## Table of Contents
- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Advanced Usage](#advanced-usage)
- [API Reference](#api-reference)
- [Security Considerations](#security-considerations)
- [Examples](#examples)

## Overview

The `Cache` class provides a simple yet robust file-based caching system for PHP applications. It supports subfolder organization, cache expiration, and automatic compression of cached content.

## Features

- File-based caching with SHA1 hashing
- Support for subfolder organization
- Automatic content compression using gzip
- Cache expiration management
- Maximum cache file limit with automatic cleanup
- Security features (.htaccess protection)
- Directory structure auto-creation

## Requirements

- PHP 7.4 or higher
- Write permissions on the cache directory
- zlib extension (for compression)

## Installation

1. Copy the `Cache.php` file to your project
2. Include the class in your PHP script:

```php
require_once 'path/to/Cache.php';
```

## Basic Usage

### Initialize the Cache

```php
// Create a cache instance with default settings
$cache = new Cache('/path/to/cache/directory');

// Or with custom max files limit
$cache = new Cache('/path/to/cache/directory', 5000);
```

### Write to Cache

```php
// Store data in cache
$cache->write('unique-key', 'Data to be cached');
```

### Read from Cache

```php
// Basic read
$data = $cache->read('unique-key');

// Read with expiration (86400 seconds = 1 day)
$data = $cache->read('unique-key', 86400);
```

### Delete Cache

```php
// Delete specific cache entry
$cache->delete('unique-key');

// Clear all cache
$cache->clearAll();
```

## Advanced Usage

### Using Subfolders

```php
// Set a subfolder for organizing cache files
$cache->setSubFolder('api-responses');
$cache->write('api-key', $apiResponse);
```

### Cache with Expiration

```php
// Write cache
$cache->write('temp-data', $data);

// Read with 1-hour expiration
$data = $cache->read('temp-data', 3600);

// Check if cache exists and is valid
if ($cache->checkCache('temp-data', 3600)) {
    // Cache is valid
}
```

### Cleanup Old Cache Files

```php
// Clear cache files older than 1 day
$cache->clear(86400);

// Clear all cache files
$cache->clear(0);
```

## API Reference

### Constructor

```php
public function __construct(string $cacheDirectory, int $maxCacheFiles = 10000)
```

### Main Methods

#### `write()`
```php
public function write(string $cacheName, string $content): void
```

#### `read()`
```php
public function read(string $cacheName, int $maxAge = 0, bool $deleteExpired = TRUE): ?string
```

#### `delete()`
```php
public function delete(string $cacheName): void
```

#### `clear()`
```php
public function clear(int $maxAge = 0): void
```

#### `clearAll()`
```php
public function clearAll(): bool
```

#### `setSubFolder()`
```php
public function setSubFolder(string $subFolder): void
```

#### `checkCache()`
```php
public function checkCache(string $cacheName, int $maxAge = 0, bool $deleteExpired = TRUE): bool
```

## Security Considerations

1. The class automatically creates a `.htaccess` file to prevent direct access to cache files
2. Cache directory should be outside the web root
3. Proper permissions should be set on the cache directory
4. Cache keys should be validated before use

## Examples

### Caching API Responses

```php
$cache = new Cache('/path/to/cache');
$cache->setSubFolder('api');

$apiUrl = 'https://api.example.com/data';
$cacheKey = 'api-' . md5($apiUrl);

// Try to get from cache first
if ($data = $cache->read($cacheKey, 3600)) {
    return json_decode($data);
}

// If not in cache, fetch and store
$response = file_get_contents($apiUrl);
$cache->write($cacheKey, $response);

return json_decode($response);
```

### Caching Database Results

```php
$cache = new Cache('/path/to/cache');
$cache->setSubFolder('db-queries');

$queryKey = 'user-list-' . md5($sql);

// Try cache first
if ($data = $cache->read($queryKey, 1800)) {
    return unserialize($data);
}

// If not in cache, query and store
$result = $db->query($sql)->fetchAll();
$cache->write($queryKey, serialize($result));

return $result;
```

### Managing Multiple Cache Types

```php
$cache = new Cache('/path/to/cache');

// API responses - 1 hour cache
$cache->setSubFolder('api');
$apiData = $cache->read('api-key', 3600);

// User sessions - 24 hour cache
$cache->setSubFolder('sessions');
$sessionData = $cache->read('session-key', 86400);

// Template fragments - 1 week cache
$cache->setSubFolder('templates');
$templateData = $cache->read('template-key', 604800);
```
