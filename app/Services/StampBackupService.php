<?php

namespace App\Services;

use App\Models\DocumentStamp;
use Illuminate\Support\Facades\Log;

/**
 * Manages unstamped PDF backups used when applying document stamps.
 * When a scanned file is replaced in edit, backups and stamp records must reset.
 */
class StampBackupService
{
    public const FILE_KEYS = ['masterlist', 'drf', 'dcn', 'distribution', 'retrieval'];

    public static function backupDir(int $requestId): string
    {
        return storage_path('app/private/stamp_backups/' . $requestId);
    }

    public static function backupPath(int $requestId, string $fileKey): string
    {
        return self::backupDir($requestId) . '/' . $fileKey . '_original.pdf';
    }

    public static function metaPath(int $requestId, string $fileKey): string
    {
        return self::backupDir($requestId) . '/' . $fileKey . '_meta.json';
    }

    /** Clear backup, metadata, and stamp DB row for a file slot. */
    public static function invalidate(int $requestId, string $fileKey): void
    {
        if (!in_array($fileKey, self::FILE_KEYS, true)) {
            return;
        }

        $backup = self::backupPath($requestId, $fileKey);
        $meta   = self::metaPath($requestId, $fileKey);

        if (file_exists($backup)) {
            @unlink($backup);
        }
        if (file_exists($meta)) {
            @unlink($meta);
        }

        DocumentStamp::where('document_request_id', $requestId)
            ->where('file_key', $fileKey)
            ->delete();

        Log::info('Stamp: invalidated backup for replaced file', [
            'request_id' => $requestId,
            'file_key'   => $fileKey,
        ]);
    }

    /**
     * Resolve the unstamped PDF to stamp from.
     * Uses backup when re-stamping; refreshes backup when the live file was replaced.
     */
    public static function resolveSource(int $requestId, string $fileKey, string $absolutePath, string $relativePath): string
    {
        $absolutePath = realpath($absolutePath) ?: $absolutePath;
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        $backupPath = self::backupPath($requestId, $fileKey);
        $meta       = self::readMeta($requestId, $fileKey);
        $currentHash = is_readable($absolutePath) ? md5_file($absolutePath) : '';

        if (file_exists($backupPath) && filesize($backupPath) > 0 && $meta) {
            $knownRelative = (string) ($meta['relative_path'] ?? '');
            $stampedHash   = (string) ($meta['stamped_hash'] ?? '');
            $originalHash  = (string) ($meta['original_hash'] ?? '');

            $pathChanged = $knownRelative !== '' && $knownRelative !== $relativePath;

            if (!$pathChanged && $stampedHash !== '' && hash_equals($stampedHash, $currentHash)) {
                return $backupPath;
            }

            if (!$pathChanged && $originalHash !== '' && hash_equals($originalHash, $currentHash)) {
                return $backupPath;
            }

            Log::info('Stamp: live file no longer matches backup — creating fresh backup', [
                'request_id'    => $requestId,
                'file_key'      => $fileKey,
                'path_changed'  => $pathChanged,
                'known_path'    => $knownRelative,
                'current_path'  => $relativePath,
            ]);

            self::invalidate($requestId, $fileKey);
        }

        return self::createBackup($requestId, $fileKey, $absolutePath, $relativePath);
    }

    /** After a successful stamp, remember the stamped file hash for re-stamp detection. */
    public static function recordStamped(int $requestId, string $fileKey, string $absolutePath, string $relativePath): void
    {
        if (!is_readable($absolutePath)) {
            return;
        }

        $meta = self::readMeta($requestId, $fileKey) ?? [];
        $meta['stamped_hash']  = md5_file($absolutePath);
        $meta['relative_path'] = ltrim(str_replace('\\', '/', $relativePath), '/');
        $meta['updated_at']    = time();

        $dir = self::backupDir($requestId);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::metaPath($requestId, $fileKey), json_encode($meta));
    }

    public static function restoreTo(int $requestId, string $fileKey, string $targetAbsolutePath): bool
    {
        $backupPath = self::backupPath($requestId, $fileKey);

        if (!file_exists($backupPath)) {
            Log::warning('Stamp: no backup found to restore', ['backup' => $backupPath]);
            return false;
        }

        return copy($backupPath, $targetAbsolutePath);
    }

    private static function createBackup(int $requestId, string $fileKey, string $absolutePath, string $relativePath): string
    {
        $backupPath = self::backupPath($requestId, $fileKey);
        $dir        = self::backupDir($requestId);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!copy($absolutePath, $backupPath)) {
            throw new \RuntimeException('Failed to create backup of original file.');
        }

        $meta = [
            'relative_path' => ltrim(str_replace('\\', '/', $relativePath), '/'),
            'original_hash' => md5_file($absolutePath),
            'stamped_hash'  => null,
            'created_at'    => time(),
        ];

        file_put_contents(self::metaPath($requestId, $fileKey), json_encode($meta));

        Log::info('Stamp: created backup', [
            'from' => $absolutePath,
            'to'   => $backupPath,
        ]);

        return $backupPath;
    }

    /** @return array<string, mixed>|null */
    private static function readMeta(int $requestId, string $fileKey): ?array
    {
        $metaPath = self::metaPath($requestId, $fileKey);
        if (!file_exists($metaPath)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($metaPath), true);

        return is_array($data) ? $data : null;
    }
}
