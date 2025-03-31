<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SftpFileReader
{
    /**
     * Test connection to the SFTP server
     *
     * @param string $serverKey The configuration key for the SFTP server
     * @return bool True if connection is successful, false otherwise
     */
    public function testConnection(string $serverKey): bool
    {
        try {
            $disk = Storage::disk($serverKey);

            // Attempt a simple operation to verify the connection
            $disk->exists('/');

            return true;
        } catch (Exception $e) {
            Log::error('SFTP Connection Test Failed: ' . $e->getMessage(), [
                'exception' => $e,
                'stacktrace' => $e->getTraceAsString(),
                'server' => $serverKey
            ]);
            return false;
        }
    }

    /**
     * Read a file from a specific SFTP server
     *
     * @param string $serverKey The configuration key for the SFTP server
     * @param string $filename The path and name of the file on the SFTP server
     * @return string|false File contents or false if file cannot be read
     */
    public function readSftpFile(string $serverKey, string $filename): bool|string
    {
        try {
            // Use the predefined disk for the specific server
            $disk = Storage::disk($serverKey);

            // Check if file exists
            if (!$disk->exists($filename)) {
                throw new Exception("File not found on server $serverKey: $filename");
            }

            // Read file contents
            return $disk->get($filename);
        } catch (Exception $e) {
            Log::error('SFTP File Read Error: ' . $e->getMessage(), [
                'exception' => $e,
                'stacktrace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * List files in a specific directory on the SFTP server
     *
     * @param string $serverKey The configuration key for the SFTP server
     * @param string $directory The directory to list files from
     * @return array|false List of files or false if error occurs
     */
    public function listFiles(string $serverKey, string $directory = '/'): bool|array
    {
        try {
            return Storage::disk($serverKey)->files($directory);
        } catch (Exception $e) {
            Log::error('SFTP File List Error: ' . $e->getMessage(), [
                'exception' => $e,
                'stacktrace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
