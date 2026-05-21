<?php

namespace Lalalili\SurveyFilament\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class AudienceFileReader
{
    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    public function read(string $path): array
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx', 'xls' => $this->readSpreadsheet($path),
            default => throw new RuntimeException('僅支援 CSV、XLSX 或 XLS 檔案。'),
        };
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException('無法讀取檔案。');
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);

            throw new RuntimeException('檔案沒有標題列。');
        }

        $columns = $this->normalizeColumns($headers);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $record = $this->combineRow($columns, $line);

            if ($record !== null) {
                $rows[] = $record;
            }
        }

        fclose($handle);

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function readSpreadsheet(string $path): array
    {
        if (! class_exists(IOFactory::class)) {
            throw new RuntimeException('目前環境缺少 PhpSpreadsheet，無法讀取 Excel 檔案。');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        if ($rawRows === [] || ! is_array($rawRows[0] ?? null)) {
            throw new RuntimeException('檔案沒有標題列。');
        }

        $columns = $this->normalizeColumns($rawRows[0]);
        $rows = [];

        foreach (array_slice($rawRows, 1) as $line) {
            $record = $this->combineRow($columns, $line);

            if ($record !== null) {
                $rows[] = $record;
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return list<string>
     */
    private function normalizeColumns(array $headers): array
    {
        $columns = [];

        foreach ($headers as $index => $header) {
            $column = trim((string) $header);

            if ($column === '') {
                $column = 'column_'.($index + 1);
            }

            if (in_array($column, $columns, true)) {
                throw new RuntimeException("欄位名稱重複：{$column}");
            }

            $columns[] = $column;
        }

        if ($columns === []) {
            throw new RuntimeException('檔案沒有可用欄位。');
        }

        return $columns;
    }

    /**
     * @param  list<string>  $columns
     * @param  array<int, mixed>  $line
     * @return array<string, mixed>|null
     */
    private function combineRow(array $columns, array $line): ?array
    {
        $record = [];
        $hasValue = false;

        foreach ($columns as $index => $column) {
            $value = $line[$index] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($value !== null && $value !== '') {
                $hasValue = true;
            }

            $record[$column] = $value;
        }

        return $hasValue ? $record : null;
    }
}
