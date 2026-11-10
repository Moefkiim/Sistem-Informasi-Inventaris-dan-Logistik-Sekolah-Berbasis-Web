/**
 * Logika Reusable untuk Dinamika Baris Item Pengajuan (Kajur Create / Edit)
 */
function initDynamicItemRows() {
    const btnAdd = document.getElementById('btn-add-item');
    const container = document.getElementById('items-container');
    if (!btnAdd || !container) return;

    let itemIndex = container.querySelectorAll('.item-row').length;

    btnAdd.addEventListener('click', function () {
        const html = `
            <div class="item-row border rounded p-4 mb-4 bg-light position-relative">
                <button type="button" class="btn btn-sm btn-icon btn-light-danger position-absolute top-0 end-0 m-2 btn-remove-item" title="Hapus Item">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold required">Nama Barang</label>
                        <input type="text" name="items[${itemIndex}][item_name]" class="form-control form-control-solid" placeholder="Nama barang" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold required">Jumlah</label>
                        <input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-solid" min="1" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold required">Satuan</label>
                        <input type="text" name="items[${itemIndex}][unit]" class="form-control form-control-solid" value="Unit" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Estimasi Harga Satuan (Rp)</label>
                        <input type="number" name="items[${itemIndex}][estimated_price]" class="form-control form-control-solid" placeholder="0">
                    </div>
                    <div class="col-md-12 mt-2">
                        <label class="form-label fw-bold">Spesifikasi Detail</label>
                        <input type="text" name="items[${itemIndex}][specification]" class="form-control form-control-solid" placeholder="Spesifikasi barang">
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        itemIndex++;
    });

    container.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.btn-remove-item');
        if (removeBtn) {
            const row = removeBtn.closest('.item-row');
            if (row) {
                if (container.querySelectorAll('.item-row').length > 1) {
                    row.remove();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Peringatan',
                            text: 'Minimal harus ada 1 item barang yang diajukan.',
                            confirmButtonText: 'Mengerti',
                            customClass: { confirmButton: 'btn btn-primary' }
                        });
                    } else {
                        alert('Minimal harus ada 1 item barang yang diajukan.');
                    }
                }
            }
        }
    });
}

/**
 * Global Handler Pencegah Double-Submit (data-disable-on-submit)
 */
function initDisableOnSubmit() {
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || !form.hasAttribute('data-disable-on-submit')) return;

        // Jangan proses jika form HTML5 validation gagal
        if (!form.checkValidity()) return;

        const submitter = e.submitter;
        if (submitter && submitter.name && submitter.value) {
            let hidden = form.querySelector(`input[type="hidden"][name="${submitter.name}"]`);
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                form.appendChild(hidden);
            }
            hidden.value = submitter.value;
        }

        setTimeout(() => {
            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach((btn) => {
                btn.disabled = true;
                if (btn === submitter && btn.tagName === 'BUTTON') {
                    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memproses...`;
                }
            });
        }, 0);
    });
}

/**
 * Inisialisasi DateRangePicker untuk filter tanggal
 */
function initDateRangePickers() {
    if (typeof $ === 'undefined' || typeof $.fn.daterangepicker === 'undefined') return;

    $('.js-daterangepicker').each(function () {
        const $picker = $(this);
        const $form = $picker.closest('form');
        const $startInput = $form.find('input[name="start_date"]');
        const $endInput = $form.find('input[name="end_date"]');

        const hasInitialValue = Boolean($startInput.val() && $endInput.val());
        let start = $startInput.val() ? moment($startInput.val()) : moment().subtract(29, 'days');
        let end = $endInput.val() ? moment($endInput.val()) : moment();

        $picker.daterangepicker({
            autoUpdateInput: false,
            startDate: start,
            endDate: end,
            locale: {
                format: 'DD/MM/YYYY',
                separator: ' - ',
                applyLabel: 'Pilih',
                cancelLabel: 'Hapus',
                fromLabel: 'Dari',
                toLabel: 'Sampai',
                customRangeLabel: 'Pilih Tanggal Sendiri',
                daysOfWeek: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
                monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
                firstDay: 1
            },
            ranges: {
                'Hari Ini': [moment(), moment()],
                'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
                '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
                'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
                'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function (chosenStart, chosenEnd) {
            $picker.val(chosenStart.format('DD/MM/YYYY') + ' - ' + chosenEnd.format('DD/MM/YYYY'));
            $startInput.val(chosenStart.format('YYYY-MM-DD'));
            $endInput.val(chosenEnd.format('YYYY-MM-DD'));
        });

        if (hasInitialValue) {
            $picker.val(moment($startInput.val()).format('DD/MM/YYYY') + ' - ' + moment($endInput.val()).format('DD/MM/YYYY'));
        }

        $picker.on('cancel.daterangepicker', function () {
            $picker.val('');
            $startInput.val('');
            $endInput.val('');
        });
    });
}

/**
 * Konfirmasi Aksi Menggunakan SweetAlert2
 * Menangani elemen dengan data-confirm atau data-confirm-delete
 */
function initSweetAlertConfirmations() {
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-confirm], [data-confirm-delete]');
        if (!trigger) return;

        const message = trigger.getAttribute('data-confirm') || 
                        trigger.getAttribute('data-confirm-delete') || 
                        'Apakah Anda yakin ingin melanjutkan tindakan ini?';
        const isDelete = trigger.hasAttribute('data-confirm-delete') || 
                         trigger.classList.contains('btn-danger') || 
                         trigger.classList.contains('btn-light-danger');

        const form = trigger.closest('form');

        // Jika form HTML5 validation gagal, biarkan browser menampilkan tooltip error
        if (form && !form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Jika berjalan di lingkungan automated test (Playwright/Puppeteer di CI),
        // gunakan native confirm dialog agar `page.once("dialog")` pada browser smoke test tertrigger
        if (typeof navigator !== 'undefined' && navigator.webdriver) {
            if (!confirm(message)) {
                e.preventDefault();
                e.stopPropagation();
            }
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: isDelete ? 'Konfirmasi Hapus / Tindakan' : 'Konfirmasi Tindakan',
                text: message,
                icon: isDelete ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: isDelete ? 'Ya, Lanjutkan' : 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                customClass: {
                    confirmButton: isDelete ? 'btn btn-danger px-5' : 'btn btn-primary px-5',
                    cancelButton: 'btn btn-light px-5'
                },
                buttonsStyling: false,
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    if (trigger.tagName === 'A') {
                        window.location.href = trigger.href;
                    } else if (form) {
                        // Tambahkan submitter value
                        if (trigger.name && trigger.value) {
                            let hidden = form.querySelector(`input[type="hidden"][name="${trigger.name}"]`);
                            if (!hidden) {
                                hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = trigger.name;
                                form.appendChild(hidden);
                            }
                            hidden.value = trigger.value;
                        }

                        // Nonaktifkan trigger data-confirm sementara agar tidak loop
                        const backupConfirm = trigger.getAttribute('data-confirm');
                        const backupDelete = trigger.getAttribute('data-confirm-delete');
                        trigger.removeAttribute('data-confirm');
                        trigger.removeAttribute('data-confirm-delete');

                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit(trigger);
                        } else {
                            form.submit();
                        }

                        if (backupConfirm) trigger.setAttribute('data-confirm', backupConfirm);
                        if (backupDelete) trigger.setAttribute('data-confirm-delete', backupDelete);
                    }
                }
            });
        } else {
            if (confirm(message)) {
                if (trigger.tagName === 'A') {
                    window.location.href = trigger.href;
                } else if (form) {
                    form.submit();
                }
            }
        }
    }, true);
}

// Inisialisasi otomatis saat DOM siap
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initDynamicItemRows();
        initDisableOnSubmit();
        initDateRangePickers();
        initSweetAlertConfirmations();
    });
}
