<?php

declare(strict_types=1);

namespace App\Services\Report;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsvExportService
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function download(string $filenamePrefix, array $headers, iterable $rows): StreamedResponse
    {
        $filename = sprintf('%s-%s.csv', $filenamePrefix, now()->format('Y-m-d-H-i-s'));

        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, $this->stringifyRow($row));
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, mixed>  $row
     * @return list<string>
     */
    private function stringifyRow(array $row): array
    {
        return array_map(
            fn (mixed $value): string => $value === null ? '' : (string) $value,
            array_values($row),
        );
    }
}
