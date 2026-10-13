<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Support\AssetCondition;
use App\Support\Departments;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Mengurai, memvalidasi, dan menulis impor inventaris awal dari berkas
 * Excel (.xlsx) atau CSV. Seluruh penulisan bersifat semua-atau-tidak.
 */
class InventoryImportService
{
    public const MAX_ROWS = 2000;

    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Pemetaan judul kolom (huruf kecil) ke kunci internal.
     */
    public const HEADERS = [
        'kode barang' => 'code',
        'nama barang' => 'name',
        'kategori' => 'category',
        'tipe' => 'item_type',
        'satuan' => 'unit',
        'jurusan' => 'department',
        'lokasi' => 'location',
        'merk' => 'brand',
        'model' => 'model',
        'nomor seri' => 'serial_number',
        'nomor inventaris unit' => 'unit_inventory_number',
        'tahun perolehan' => 'acquisition_year',
        'harga perolehan' => 'acquisition_price',
        'kondisi' => 'current_condition',
        'status' => 'status',
        'stok awal' => 'stock',
        'stok minimum' => 'minimum_stock',
    ];

    /**
     * Kunci kolom yang wajib ada pada baris judul.
     *
     * @var array<string,string>
     */
    private const REQUIRED_HEADERS = ['name' => 'Nama Barang', 'item_type' => 'Tipe'];

    public function __construct(private readonly InventoryRegistrationService $registration) {}

    /**
     * Mengurai dan memvalidasi berkas tanpa menulis apa pun.
     *
     * @return array{items:array<int,array<string,mixed>>,errors:array<int,array<string,string|int>>,summary:array<string,int>}
     */
    public function analyze(string $path): array
    {
        $raw = $this->read($path);

        if ($raw['error'] !== null) {
            return $this->result([], [['row' => 0, 'column' => '-', 'message' => $raw['error']]]);
        }

        [$headerMap, $headerErrors] = $this->resolveHeaders($raw['headers']);

        if ($headerErrors !== []) {
            return $this->result([], $headerErrors);
        }

        $rows = $this->dataRows($raw['rows']);

        if (count($rows) > self::MAX_ROWS) {
            return $this->result([], [[
                'row' => 0,
                'column' => '-',
                'message' => 'Jumlah baris melebihi batas '.self::MAX_ROWS.' baris ('.count($rows).' baris).',
            ]]);
        }

        return $this->build($rows, $headerMap);
    }

    /**
     * Menulis seluruh barang dan unit dalam satu transaksi.
     *
     * @param  array{items:array<int,array<string,mixed>>,errors:array<int,array<mixed>>}  $analysis
     * @return array{batch:string,items:int,units:int}
     */
    public function commit(array $analysis, User $actor): array
    {
        if (! empty($analysis['errors'])) {
            throw new \RuntimeException('Tidak dapat mengimpor selama masih ada kesalahan validasi.');
        }

        $batch = 'IMP-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
        $items = 0;
        $units = 0;

        foreach ($analysis['items'] as $group) {
            $this->registration->register($group['data'], $actor, $group['units'], false);
            $items++;
            $units += count($group['units']);
        }

        ActivityLog::log(
            'inventory_imported',
            "Impor inventaris {$batch}: {$items} barang, {$units} unit.",
            null,
            [],
            ['batch' => $batch, 'items' => $items, 'units' => $units]
        );

        return ['batch' => $batch, 'items' => $items, 'units' => $units];
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @param  array<int,array<string,mixed>>  $errors
     * @param  array<int,array<string,mixed>>  $warnings
     * @return array{items:array<int,array<string,mixed>>,errors:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,summary:array<string,int>}
     */
    private function result(array $items, array $errors, array $warnings = []): array
    {
        return [
            'items' => $items,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'barang' => count($items),
                'unit' => array_sum(array_map(fn ($group) => count($group['units']), $items)),
                'errors' => count($errors),
                'warnings' => count($warnings),
            ],
        ];
    }

    /**
     * @return array{error:?string,headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    private function read(string $path): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        try {
            $reader = $extension === 'csv' ? new Csv : new Xlsx;
            $reader->setReadDataOnly(true);

            if ($reader instanceof Csv) {
                $reader->setInputEncoding('UTF-8');
            }

            $spreadsheet = $reader->load($path);
        } catch (\Throwable $e) {
            return ['error' => 'Berkas tidak dapat dibaca: '.$e->getMessage(), 'headers' => [], 'rows' => []];
        }

        $matrix = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if ($matrix === []) {
            return ['error' => 'Berkas tidak memiliki data.', 'headers' => [], 'rows' => []];
        }

        $headers = array_map(fn ($value) => trim((string) $value), array_shift($matrix));

        return ['error' => null, 'headers' => $headers, 'rows' => array_values($matrix)];
    }

    /**
     * @param  array<int,array<int,mixed>>  $rows
     * @return array<int,array{row:int,cells:array<int,string>}>
     */
    private function dataRows(array $rows): array
    {
        $data = [];

        foreach ($rows as $index => $row) {
            $cells = array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', $row);

            if (implode('', $cells) === '') {
                continue;
            }

            $data[] = ['row' => $index + 2, 'cells' => array_values($cells)];
        }

        return $data;
    }

    /**
     * @param  array<int,string>  $headers
     * @return array{0:array<int,string>,1:array<int,array<string,mixed>>}
     */
    private function resolveHeaders(array $headers): array
    {
        $headerMap = [];
        $found = [];

        foreach ($headers as $index => $header) {
            $key = self::HEADERS[strtolower(trim($header))] ?? null;

            if ($key !== null) {
                $headerMap[$index] = $key;
                $found[$key] = true;
            }
        }

        $errors = [];

        foreach (['name' => 'Nama Barang', 'item_type' => 'Tipe'] as $key => $label) {
            if (! isset($found[$key])) {
                $errors[] = ['row' => 0, 'column' => $label, 'message' => 'Kolom wajib "'.$label.'" tidak ditemukan pada baris judul.'];
            }
        }

        return [$headerMap, $errors];
    }

    /**
     * @param  array<int,array{row:int,cells:array<int,string>}>  $rows
     * @param  array<int,string>  $headerMap
     * @return array{items:array<int,array<string,mixed>>,errors:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,summary:array<string,int>}
     */
    private function build(array $rows, array $headerMap): array
    {
        $locations = Location::query()->pluck('id', 'name');
        $errors = [];
        $warnings = [];
        $groups = [];
        $groupIndexByCode = [];
        $consumableCodes = [];
        $seenSerials = [];
        $seenUnitNumbers = [];

        foreach ($rows as $row) {
            $values = [];

            foreach ($headerMap as $index => $key) {
                $values[$key] = $row['cells'][$index] ?? '';
            }

            $rowNumber = $row['row'];
            $rowErrors = [];
            $add = function (string $column, string $message) use (&$rowErrors, $rowNumber): void {
                $rowErrors[] = ['row' => $rowNumber, 'column' => $column, 'message' => $message];
            };

            $name = trim($values['name'] ?? '');

            if ($name === '') {
                $add('Nama Barang', 'Nama barang wajib diisi.');
            }

            $itemType = $this->normalizeItemType($values['item_type'] ?? '');

            if ($itemType === null) {
                $add('Tipe', 'Tipe harus "individual" atau "habis pakai".');
            }

            $department = null;

            if (($values['department'] ?? '') !== '') {
                $department = $this->normalizeDepartment($values['department']);

                if ($department === null) {
                    $add('Jurusan', 'Kode jurusan tidak dikenal: "'.$values['department'].'".');
                }
            }

            $locationId = null;

            if (($values['location'] ?? '') !== '') {
                $locationName = trim($values['location']);
                $locationId = $locations[$locationName] ?? null;

                if ($locationId === null) {
                    $add('Lokasi', 'Lokasi tidak ditemukan di master lokasi: "'.$locationName.'".');
                }
            }

            $rawCondition = trim($values['current_condition'] ?? '');
            $condition = $this->registration->normalizeCondition($rawCondition);

            if ($rawCondition !== '' && $condition === null) {
                $add('Kondisi', 'Kondisi tidak dikenal. Nilai valid: '.implode(', ', array_values(AssetCondition::LABELS)).'.');
            }

            $condition ??= AssetCondition::BAIK;

            $rawStatus = trim($values['status'] ?? '');
            $status = $this->normalizeStatus($rawStatus);

            if ($rawStatus !== '' && $status === null) {
                $add('Status', 'Status tidak dikenal atau tidak boleh diimpor. Nilai valid: '.implode(', ', array_map(fn ($s) => AssetUnit::STATUS_LABELS[$s], AssetUnit::IMPORTABLE_STATUSES)).'. Status "Dipinjam"/"Dihapuskan" hanya lewat alur peminjaman/penghapusan.');
            }

            $status ??= 'aktif';

            // Invarian kondisi–status: kondisi hilang / tidak layak pakai
            // memaksa unit menjadi tidak aktif walau berkas menuliskan aktif.
            $state = AssetUnit::normalizeInitialState($condition, $status);

            if ($state['adjusted']) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'column' => 'Status',
                    'message' => 'Baris '.$rowNumber.': kondisi '.AssetCondition::label($condition).' tidak boleh berstatus '.AssetUnit::STATUS_LABELS[$status].'; disimpan sebagai '.AssetUnit::STATUS_LABELS[$state['status']].'.',
                ];
                $status = $state['status'];
            }

            $year = null;

            if (trim($values['acquisition_year'] ?? '') !== '') {
                if (! preg_match('/^\d{4}$/', trim($values['acquisition_year']))
                    || (int) $values['acquisition_year'] < 1900
                    || (int) $values['acquisition_year'] > (int) date('Y') + 1) {
                    $add('Tahun Perolehan', 'Tahun perolehan harus angka 4 digit antara 1900 dan '.(date('Y') + 1).'.');
                } else {
                    $year = (int) $values['acquisition_year'];
                }
            }

            $price = null;

            if (trim($values['acquisition_price'] ?? '') !== '') {
                $normalizedPrice = str_replace(['.', ','], ['', '.'], trim($values['acquisition_price']));

                if (! is_numeric($normalizedPrice) || (float) $normalizedPrice < 0) {
                    $add('Harga Perolehan', 'Harga perolehan harus berupa angka tidak negatif.');
                } else {
                    $price = (float) $normalizedPrice;
                }
            }

            $stock = 0;

            if (trim($values['stock'] ?? '') !== '') {
                if (! preg_match('/^\d+$/', trim($values['stock']))) {
                    $add('Stok Awal', 'Stok awal harus berupa bilangan bulat tidak negatif.');
                } else {
                    $stock = (int) $values['stock'];
                }
            }

            $minimumStock = null;

            if (trim($values['minimum_stock'] ?? '') !== '') {
                if (! preg_match('/^\d+$/', trim($values['minimum_stock']))) {
                    $add('Stok Minimum', 'Stok minimum harus berupa bilangan bulat tidak negatif.');
                } else {
                    $minimumStock = (int) $values['minimum_stock'];
                }
            }

            $code = trim($values['code'] ?? '');
            $code = $code === '' ? null : $code;

            if ($code !== null) {
                if (Item::withTrashed()->where('code', $code)->exists()) {
                    $add('Kode Barang', 'Kode barang sudah terdaftar: "'.$code.'".');
                } elseif ($itemType === 'consumable' && isset($consumableCodes[$code])) {
                    $add('Kode Barang', 'Kode barang "'.$code.'" dipakai lebih dari sekali untuk barang habis pakai.');
                } elseif ($itemType === 'consumable') {
                    $consumableCodes[$code] = true;
                }
            }

            $serial = trim($values['serial_number'] ?? '');

            if ($serial !== '') {
                if (isset($seenSerials[$serial])) {
                    $add('Nomor Seri', 'Nomor seri duplikat di dalam berkas: "'.$serial.'".');
                } elseif (AssetUnit::withTrashed()->where('serial_number', $serial)->exists()) {
                    $add('Nomor Seri', 'Nomor seri sudah terdaftar: "'.$serial.'".');
                }

                $seenSerials[$serial] = true;
            }

            $unitNumber = trim($values['unit_inventory_number'] ?? '');

            if ($unitNumber !== '') {
                if (isset($seenUnitNumbers[$unitNumber])) {
                    $add('Nomor Inventaris Unit', 'Nomor inventaris unit duplikat di dalam berkas: "'.$unitNumber.'".');
                } elseif (AssetUnit::withTrashed()->where('unit_inventory_number', $unitNumber)->exists()) {
                    $add('Nomor Inventaris Unit', 'Nomor inventaris unit sudah terdaftar: "'.$unitNumber.'".');
                }

                $seenUnitNumbers[$unitNumber] = true;
            }

            if ($rowErrors !== []) {
                $errors = array_merge($errors, $rowErrors);

                continue;
            }

            $itemData = [
                'code' => $code,
                'name' => trim($values['name']),
                'category' => trim($values['category'] ?? '') !== '' ? trim($values['category']) : null,
                'item_type' => $itemType,
                'unit' => trim($values['unit'] ?? '') !== '' ? trim($values['unit']) : null,
                'brand' => trim($values['brand'] ?? '') !== '' ? trim($values['brand']) : null,
                'model' => trim($values['model'] ?? '') !== '' ? trim($values['model']) : null,
                'department' => $this->normalizeDepartment($values['department'] ?? ''),
                'location_id' => $locationId,
                'acquisition_year' => $year,
                'acquisition_price' => $price,
                'current_condition' => $condition,
                'current_status' => $status,
                'minimum_stock' => $minimumStock ?? 0,
                'stock' => $itemType === 'consumable' ? $stock : 0,
                'source' => 'pembelian',
            ];

            $unitData = [
                'unit_inventory_number' => $unitNumber !== '' ? $unitNumber : null,
                'serial_number' => $serial !== '' ? $serial : null,
                'current_condition' => $condition,
                'current_status' => $status,
                'location_id' => $locationId,
            ];

            if ($itemType === 'individual' && $code !== null && isset($groupIndexByCode[$code])) {
                $groups[$groupIndexByCode[$code]]['units'][] = $unitData;

                continue;
            }

            $group = ['data' => $itemData, 'units' => $itemType === 'individual' ? [$unitData] : []];

            if ($itemType === 'individual' && $code !== null) {
                $groupIndexByCode[$code] = count($groups);
            }

            $groups[] = $group;
        }

        return $this->result($groups, $errors, $warnings);
    }

    private function normalizeItemType(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'individual' => 'individual',
            'habis pakai', 'habis_pakai', 'consumable' => 'consumable',
            default => null,
        };
    }

    private function normalizeDepartment(string $value): ?string
    {
        $needle = strtoupper(trim($value));

        foreach (Departments::codes() as $code) {
            if ($needle === strtoupper((string) $code)) {
                return $code;
            }
        }

        return null;
    }

    private function normalizeStatus(string $value): ?string
    {
        $needle = strtolower(trim($value));

        foreach (AssetUnit::STATUS_LABELS as $canonical => $label) {
            if (($needle === $canonical || $needle === strtolower($label))
                && AssetUnit::isImportableStatus($canonical)) {
                return $canonical;
            }
        }

        return null;
    }
}
