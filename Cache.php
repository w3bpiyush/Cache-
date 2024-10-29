<?php

/**
 * Class Cache
 * A simple file-based caching system with support for subfolders, cache expiration, and compression.
 */
class Cache
{
    /**
     * @var string|null Directory where cache files will be stored.
     */
    private ?string $cacheDirectory = NULL;

    /**
     * @var string|null Optional subfolder within the main cache directory.
     */
    private ?string $subFolder = NULL;

    /**
     * @var int Maximum number of cache files allowed; older files will be deleted if limit is exceeded.
     */
    private int $maxCacheFiles;

    /**
     * Cache constructor.
     *
     * @param string $cacheDirectory Path to the directory where cache files should be stored.
     * @param int $maxCacheFiles Optional, maximum cache files allowed before deletion of the oldest file.
     */
    public function __construct(string $cacheDirectory, int $maxCacheFiles = 10000)
    {
        $this->cacheDirectory = $cacheDirectory;
        $this->maxCacheFiles = $maxCacheFiles;

        $this->createDirectory($this->cacheDirectory);
        $this->createDefaultFiles($this->cacheDirectory);
    }

    /**
     * Sets a subfolder within the main cache directory.
     *
     * @param string $subFolder Name of the subfolder.
     */
    public function setSubFolder(string $subFolder): void
    {
        $this->subFolder = $subFolder;
        $this->createDirectory($this->getCacheDir());
    }

    /**
     * Reads a cache file if it exists and has not expired.
     *
     * @param string $cacheName Unique identifier for the cache entry.
     * @param int $maxAge Maximum age of the cache in seconds; defaults to 0 for indefinite cache.
     * @param bool $deleteExpired Whether to delete expired cache files.
     * @return string|null Uncompressed content of the cache file, or NULL if cache is expired or missing.
     */
    public function read(string $cacheName, int $maxAge = 0, bool $deleteExpired = TRUE): ?string
    {
        $cacheFile = $this->getCachePath($cacheName);

        if ($this->checkCache($cacheName, $maxAge, $deleteExpired)) {
            $data = file_get_contents($cacheFile);
            return $data ? gzuncompress($data) : NULL;
        }

        return NULL;
    }

    /**
     * Writes data to a cache file, compressing it and enforcing the cache file limit.
     *
     * @param string $cacheName Unique identifier for the cache entry.
     * @param string $content Content to be cached.
     */
    public function write(string $cacheName, string $content): void
    {
        $cacheFile = $this->getCachePath($cacheName);
        $compressedContent = gzcompress($content);

        file_put_contents($cacheFile, $compressedContent, LOCK_EX);
        $this->enforceCacheLimit();
    }

    /**
     * Enforces the maximum cache file limit by deleting the oldest file if limit is exceeded.
     */
    private function enforceCacheLimit(): void
    {
        $files = glob($this->getCacheDir() . '/*.cache');
        if (count($files) > $this->maxCacheFiles) {
            usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
            unlink($files[0]); // Delete the oldest file
        }
    }

    /**
     * Checks if a cache file exists and is within the allowed age.
     *
     * @param string $cacheName Unique identifier for the cache entry.
     * @param int $maxAge Maximum age of the cache in seconds; defaults to 0 for indefinite cache.
     * @param bool $deleteExpired Whether to delete expired cache files.
     * @return bool TRUE if cache is valid, FALSE if expired or missing.
     */
    public function checkCache(string $cacheName, int $maxAge = 0, bool $deleteExpired = TRUE): bool
    {
        $cacheFile = $this->getCachePath($cacheName);

        if (file_exists($cacheFile)) {
            if ($maxAge == 0 || (time() - filemtime($cacheFile)) <= $maxAge) {
                return TRUE;
            } elseif ($deleteExpired) {
                $this->delete($cacheName);
            }
        }
        return FALSE;
    }

    /**
     * Deletes a specific cache file.
     *
     * @param string $cacheName Unique identifier for the cache entry.
     */
    public function delete(string $cacheName): void
    {
        $cacheFile = $this->getCachePath($cacheName);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Clears all cache files older than a specified age.
     *
     * @param int $maxAge Maximum age of cache files to retain, in seconds; 0 for clearing all.
     */
    public function clear(int $maxAge = 0): void
    {
        $iterator = new DirectoryIterator($this->getCacheDir());
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile() && ($maxAge == 0 || (time() - $fileinfo->getMTime()) >= $maxAge)) {
                unlink($fileinfo->getRealPath());
            }
        }
    }

    /**
     * Deletes all cache files and subdirectories within the cache directory.
     *
     * @return bool TRUE if successful, FALSE if failed.
     */
    public function clearAll(): bool
    {
        return $this->deleteDirectory($this->getCacheDir());
    }

    /**
     * Creates a directory if it does not exist.
     *
     * @param string $directory Path to the directory.
     */
    private function createDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * Creates default files in a cache directory, including .htaccess and index.html for security.
     *
     * @param string $directory Path to the directory.
     */
    private function createDefaultFiles(string $directory): void
    {
        $htaccessPath = $directory . DIRECTORY_SEPARATOR . ".htaccess";
        $indexPath = $directory . DIRECTORY_SEPARATOR . "index.html";

        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "deny from all");
        }

        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, "");
        }
    }

    /**
     * Generates the full path for a cache file based on its hash and subdirectory.
     *
     * @param string $cacheName Unique identifier for the cache entry.
     * @return string Full path of the cache file.
     */
    private function getCachePath(string $cacheName): string
    {
        $hash = hash('sha1', $cacheName);
        $subDir = substr($hash, 0, 2);

        $this->createDirectory($this->getCacheDir() . DIRECTORY_SEPARATOR . $subDir);

        return $this->getCacheDir() . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . $hash . ".cache";
    }

    /**
     * Returns the current cache directory, with optional subfolder.
     *
     * @return string Path of the cache directory.
     */
    private function getCacheDir(): string
    {
        return ($this->subFolder != NULL)
            ? $this->cacheDirectory . DIRECTORY_SEPARATOR . $this->subFolder
            : $this->cacheDirectory;
    }

    /**
     * Recursively deletes a directory and its contents.
     *
     * @param string $dir Path to the directory to delete.
     * @return bool TRUE if successful, FALSE if failed.
     */
    private function deleteDirectory($dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }
}