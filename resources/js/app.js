/**
 * Logika Reusable untuk Dinamika Baris Item Pengajuan (Kajur Create / Edit)
 */
export function initDynamicItemRows() {
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
                    alert('Minimal harus ada 1 item barang yang diajukan.');
                }
            }
        }
    });
}

/**
 * Global Handler Pencegah Double-Submit (data-disable-on-submit)
 */
export function initDisableOnSubmit() {
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

// Inisialisasi otomatis saat DOM siap
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initDynamicItemRows();
        initDisableOnSubmit();
    });
}
