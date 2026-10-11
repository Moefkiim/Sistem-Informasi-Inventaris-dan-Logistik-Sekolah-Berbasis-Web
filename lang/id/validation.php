<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Pesan Validasi
    |--------------------------------------------------------------------------
    |
    | Pesan-pesan ini dipakai oleh kelas validator. Beberapa aturan memiliki
    | beberapa versi, misalnya aturan ukuran, sehingga pesan disesuaikan
    | dengan tipe data yang divalidasi.
    |
    */

    'accepted' => ':attribute harus diterima.',
    'active_url' => ':attribute bukan URL yang valid.',
    'after' => ':attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => ':attribute harus berupa tanggal setelah atau sama dengan :date.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'array' => ':attribute harus berupa array.',
    'before' => ':attribute harus berupa tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus berupa tanggal sebelum atau sama dengan :date.',

    'between' => [
        'array' => ':attribute harus memiliki antara :min sampai :max item.',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min sampai :max.',
        'string' => ':attribute harus berisi antara :min sampai :max karakter.',
    ],

    'boolean' => 'Kolom :attribute harus berupa benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus berupa tanggal yang sama dengan :date.',
    'date_format' => ':attribute tidak cocok dengan format :format.',
    'declined' => ':attribute harus ditolak.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
    'dimensions' => ':attribute memiliki dimensi gambar yang tidak valid.',
    'distinct' => ':attribute memiliki nilai yang duplikat.',
    'email' => ':attribute harus berupa alamat surel yang valid.',
    'ends_with' => ':attribute harus diakhiri salah satu dari: :values.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => ':attribute harus berupa sebuah berkas.',
    'filled' => 'Kolom :attribute wajib diisi.',

    'gt' => [
        'array' => ':attribute harus memiliki lebih dari :value item.',
        'file' => ':attribute harus berukuran lebih dari :value kilobyte.',
        'numeric' => ':attribute harus bernilai lebih dari :value.',
        'string' => ':attribute harus berisi lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus memiliki :value item atau lebih.',
        'file' => ':attribute harus berukuran minimal :value kilobyte.',
        'numeric' => ':attribute harus bernilai minimal :value.',
        'string' => ':attribute harus berisi minimal :value karakter.',
    ],

    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => 'Kolom :attribute tidak ada di dalam :other.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'ip' => ':attribute harus berupa alamat IP yang valid.',
    'ipv4' => ':attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => ':attribute harus berupa alamat IPv6 yang valid.',
    'json' => ':attribute harus berupa teks JSON yang valid.',

    'lt' => [
        'array' => ':attribute harus memiliki kurang dari :value item.',
        'file' => ':attribute harus berukuran kurang dari :value kilobyte.',
        'numeric' => ':attribute harus bernilai kurang dari :value.',
        'string' => ':attribute harus berisi kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh lebih dari :value item.',
        'file' => ':attribute tidak boleh lebih dari :value kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :value.',
        'string' => ':attribute tidak boleh lebih dari :value karakter.',
    ],

    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],

    'mimes' => ':attribute harus berupa berkas dengan tipe: :values.',
    'mimetypes' => ':attribute harus berupa berkas dengan tipe: :values.',

    'min' => [
        'array' => ':attribute harus memiliki minimal :min item.',
        'file' => ':attribute harus berukuran minimal :min kilobyte.',
        'numeric' => ':attribute harus bernilai minimal :min.',
        'string' => ':attribute harus berisi minimal :min karakter.',
    ],

    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'password' => 'Kata sandi salah.',
    'present' => 'Kolom :attribute wajib ada.',
    'prohibited' => 'Kolom :attribute dilarang diisi.',
    'prohibited_if' => 'Kolom :attribute dilarang diisi bila :other bernilai :value.',
    'prohibited_unless' => 'Kolom :attribute dilarang diisi kecuali :other bernilai :values.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_array_keys' => 'Kolom :attribute harus memuat entri untuk: :values.',
    'required_if' => 'Kolom :attribute wajib diisi bila :other bernilai :value.',
    'required_unless' => 'Kolom :attribute wajib diisi kecuali :other bernilai :values.',
    'required_with' => 'Kolom :attribute wajib diisi bila :values ada.',
    'required_with_all' => 'Kolom :attribute wajib diisi bila :values ada.',
    'required_without' => 'Kolom :attribute wajib diisi bila :values tidak ada.',
    'required_without_all' => 'Kolom :attribute wajib diisi bila tidak ada satu pun dari :values.',
    'same' => ':attribute dan :other harus sama.',

    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus berisi :size karakter.',
    ],

    'starts_with' => ':attribute harus dimulai salah satu dari: :values.',
    'string' => ':attribute harus berupa teks.',
    'timezone' => ':attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',
    'url' => 'Format :attribute tidak valid.',
    'uuid' => ':attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi Kustom
    |--------------------------------------------------------------------------
    |
    | Di sini Anda dapat menetapkan pesan validasi kustom untuk atribut dengan
    | aturan "atribut.aturan" yang dipakai di aplikasi.
    |
    */

    'custom' => [
        'password' => [
            'min' => 'Kata sandi minimal :min karakter.',
            'letters' => 'Kata sandi harus mengandung setidaknya satu huruf.',
            'numbers' => 'Kata sandi harus mengandung setidaknya satu angka.',
            'mixed' => 'Kata sandi harus mengandung setidaknya satu huruf besar dan satu huruf kecil.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama Atribut Validasi
    |--------------------------------------------------------------------------
    |
    | Baris berikut mengganti nama field teknis dengan istilah yang mudah
    | dipahami pengguna pada pesan validasi.
    |
    */

    'attributes' => [
        'login' => 'username atau surel',
        'password' => 'kata sandi',
        'name' => 'nama',
        'username' => 'username',
        'email' => 'surel',
        'role' => 'peran',
        'department' => 'jurusan',
        'code' => 'kode',
        'building' => 'gedung',
        'description' => 'deskripsi',
        'title' => 'judul',
        'purpose' => 'tujuan',
        'category' => 'kategori',
        'file' => 'berkas',
        'type' => 'jenis laporan',
        'start_date' => 'tanggal mulai',
        'end_date' => 'tanggal akhir',
        'action' => 'tindakan',
        'notes' => 'catatan',

        'item_id' => 'barang',
        'quantity' => 'jumlah',
        'source' => 'sumber',
        'source_origin' => 'asal barang',
        'entry_date' => 'tanggal masuk',
        'exit_date' => 'tanggal keluar',
        'reason' => 'alasan',
        'to_location_id' => 'lokasi tujuan',
        'recipient_department' => 'jurusan penerima',
        'recipient_name' => 'nama penerima',
        'distribution_date' => 'tanggal distribusi',

        'borrower_name' => 'nama peminjam',
        'borrower_department' => 'jurusan peminjam',
        'borrower_user_id' => 'pengguna peminjam',
        'asset_unit_id' => 'unit barang',
        'loan_date' => 'tanggal pinjam',
        'due_date' => 'batas pengembalian',
        'condition_on_return' => 'kondisi saat dikembalikan',

        'inventory_number' => 'nomor inventaris',
        'serial_number' => 'nomor seri',
        'brand' => 'merek',
        'model' => 'model',
        'item_type' => 'tipe barang',
        'unit' => 'satuan',
        'stock' => 'stok',
        'minimum_stock' => 'stok minimum',
        'acquisition_year' => 'tahun perolehan',
        'acquisition_price' => 'harga perolehan',
        'location_id' => 'lokasi',
        'current_condition' => 'kondisi',
        'to_condition' => 'kondisi tujuan',

        'items' => 'rincian barang',
        'items.*.item_name' => 'nama barang',
        'items.*.quantity' => 'jumlah barang',
        'items.*.unit' => 'satuan barang',
        'items.*.estimated_price' => 'perkiraan harga',
        'items.*.specification' => 'spesifikasi',

        'sarpras_notes' => 'catatan Sarpras',
        'principal_notes' => 'catatan Kepala Sekolah',
    ],

];
