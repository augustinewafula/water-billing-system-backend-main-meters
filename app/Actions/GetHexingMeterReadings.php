<?php

namespace App\Actions;

use App\Services\SftpFileReader;
use Illuminate\Support\Facades\Log;

class GetHexingMeterReadings
{
    protected SftpFileReader $ftpFileReader;

    public function __construct(SftpFileReader $ftpFileReader)
    {
        $this->ftpFileReader = $ftpFileReader;
    }

    public function execute(): array
    {
        // Get the list of files from the SFTP server
        $files = $this->ftpFileReader->listFiles('hexing_server');

        if (empty($files)) {
            Log::error('No files found on SFTP server');
            return [];
        }

        // Filter files that match the expected pattern (timestamp format)
        $csvFiles = array_filter($files, function($file) {
            return preg_match('/^\d{14}\.csv$/', basename($file));
        });

        if (empty($csvFiles)) {
            Log::error('No files matching the expected pattern found on SFTP server');
            return [];
        }

        // Sort files by name (timestamp) in descending order to get the most recent
        rsort($csvFiles);

        // Get the latest file
        $latestFile = $csvFiles[0];

        Log::info('Using latest file for meter readings', ['file' => $latestFile]);

        // Read the file contents
        $fileContents = $this->ftpFileReader->readSftpFile('hexing_server', basename($latestFile));

        if (!$fileContents) {
            Log::error('Failed to read file contents', ['file' => $latestFile]);
            return [];
        }

        // Process the file contents to extract the required data
        $processedData = $this->processMeterReadings($fileContents);

        Log::info('Processed meter readings', $processedData);

        return $processedData;
    }

    /**
     * Process the CSV content to extract unique meter readings with latest timestamps
     * and all available columns with human-readable names
     *
     * @param string $fileContents The raw CSV file contents
     * @return array Processed meter readings with unique meter_asset_no and latest tv
     */
    private function processMeterReadings(string $fileContents): array
    {
        $lines = explode("\n", $fileContents);

        // Skip empty file
        if (empty($lines)) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));

        // Define column name mappings (from original to human-readable)
        $columnMappings = [
            'meter_asset_no' => 'meter_number',
            'tv' => 'timestamp',
            'battery_voltage' => 'battery_voltage',
            'pressure_in_pipe' => 'pipe_pressure',
            'POSITIVE_CUMUL_FLOW_D' => 'positive_cumulative_flow',
            'REVERSE_CUMUL_FLOW_D' => 'reverse_cumulative_flow',
            'valve_status' => 'valve_status',
            'remaining_flow' => 'remaining_flow',
            'max_flow_d' => 'maximum_flow',
            'max_flow_d_time' => 'maximum_flow_time',
            'SS_FLOW' => 'steady_state_flow',
            'current_cumul_flow' => 'current_cumulative_flow'
        ];

        // Find the index of required fields for comparison
        $meterAssetNoIndex = array_search('meter_asset_no', $headers);
        $tvIndex = array_search('tv', $headers);

        // Check if required columns exist
        if ($meterAssetNoIndex === false || $tvIndex === false) {
            Log::error('Required columns not found in CSV file', [
                'headers' => $headers,
                'required' => ['meter_asset_no', 'tv', 'current_cumul_flow']
            ]);
            return [];
        }

        $meterReadings = [];

        // Process each line of the CSV
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $data = str_getcsv($line);

            // Skip if we don't have enough columns
            if (count($data) < count($headers)) {
                continue;
            }

            $meterAssetNo = $data[$meterAssetNoIndex];
            $tv = $data[$tvIndex];

            // Create an associative array with all columns, using human-readable names
            $rowData = [];
            foreach ($headers as $index => $header) {
                // Use the mapping if available, otherwise keep the original name
                $mappedHeader = $columnMappings[$header] ?? $header;
                $rowData[$mappedHeader] = $data[$index] ?? '';
            }

            // If we haven't seen this meter before, or this reading is newer than what we have
            if (!isset($meterReadings[$meterAssetNo]) ||
                strtotime($tv) > strtotime($meterReadings[$meterAssetNo]['timestamp'])) {
                // Store the full row data
                $meterReadings[$meterAssetNo] = $rowData;
            }
        }

        // Convert to indexed array for return
        return array_values($meterReadings);
    }
}
