#!/usr/bin/env python3
"""Smoke test browser nyata untuk SIILS (CI).

Menjalankan Chromium lewat Playwright (Python) terhadap aplikasi Laravel
yang sedang berjalan, memverifikasi:

1. Penelusuran halaman utama tiap role (Kajur, Sarpras, Kepala Sekolah)
   pada viewport 1366x768, 768x1024, dan 375x812: status 200, tanpa
   console.error/pageerror, tanpa overflow horizontal, dan tanpa teks
   "SQLSTATE", "Whoops", atau "Exception".
2. Skenario interaktif alur pengajuan:
   Kajur buat+kirim -> Sarpras tinjau -> Kepsek setujui ->
   Sarpras realisasi (toggle mode new/existing + Select2, kategori kosong
   harus lolos), plus lonceng notifikasi.
3. Halaman error khusus 403/404 dan pesan throttle login (6 gagal).
4. `.select2-container` benar-benar terinisialisasi pada dropdown barang
   (Barang Keluar, Distribusi, Peminjaman, Realisasi).

Setiap halaman di-screenshot dan disimpan ke --artifact-dir bersama indeks
(role, halaman, viewport) serta trace untuk skenario interaktif. Berhenti
dengan kode keluar != 0 bila ada pelanggaran.
"""

import argparse
import json
import os
import re
import sys
import time
from datetime import datetime
from pathlib import Path
from urllib.parse import urljoin

from playwright.sync_api import (
    TimeoutError as PlaywrightTimeoutError,
    sync_playwright,
)

# ---------------------------------------------------------------------------
# Pola teks yang menandai halaman error tak terduga (server-side crash).
# ---------------------------------------------------------------------------
CRASH_MARKERS = ("SQLSTATE", "Whoops", "Exception")

# ---------------------------------------------------------------------------
# Gerbang login: route POST /login memakai throttle:5,1 dihitung per IP
# (sukses + gagal). Semua login di CI berasal dari 127.0.0.1, jadi maksimal
# 5 POST /login dalam 60 detik. Gerbang ini menunda login berikutnya bila
# sudah terpakai 5 slot agar tidak kena throttle secara tidak sengaja.
# ---------------------------------------------------------------------------
class LoginGate:
    def __init__(self):
        self.attempt_times = []

    def wait_slot(self):
        now = time.time()
        self.attempt_times = [t for t in self.attempt_times if now - t < 60]
        while len(self.attempt_times) >= 5:
            oldest = self.attempt_times[0]
            wait_for = oldest + 60 - now + 1
            print(f"[LoginGate] menunggu {wait_for:.0f}s agar throttle login melonggar…")
            time.sleep(wait_for)
            now = time.time()
            self.attempt_times = [t for t in self.attempt_times if now - t < 60]
        self.attempt_times.append(time.time())

# ---------------------------------------------------------------------------
# Viewport yang diuji.
# ---------------------------------------------------------------------------
VIEWPORTS = [(1366, 768), (768, 1024), (375, 812)]

# ---------------------------------------------------------------------------
# Halaman per role. {slug} diisi saat runtime dari daftar (detail pertama).
# ---------------------------------------------------------------------------
ROLE_PAGES = {
    "kajur_rpl": [
        ("/home", "dashboard"),
        ("/kajur/submissions", "daftar_pengajuan"),
        ("/kajur/submissions/create", "buat_pengajuan"),
        ("/kajur/submissions/{sid}", "detail_pengajuan"),
        ("/kajur/inventory", "inventaris_jurusan"),
        ("/kajur/inventory/{iid}", "detail_inventaris"),
        ("/documents", "dokumen"),
        ("/reports", "laporan"),
        ("/notifications", "notifikasi"),
        ("/profile", "akun_saya"),
    ],
    "sarpras": [
        ("/home", "dashboard"),
        ("/sarpras/inventory", "master_barang"),
        ("/sarpras/inventory/create", "registrasi_barang"),
        ("/sarpras/inventory/{iid}", "detail_barang"),
        ("/sarpras/locations", "master_lokasi"),
        ("/sarpras/logistics/incoming", "barang_masuk"),
        ("/sarpras/logistics/outgoing", "barang_keluar"),
        ("/sarpras/logistics/distributions", "distribusi"),
        ("/sarpras/loans", "peminjaman"),
        ("/sarpras/loans/create", "buat_peminjaman"),
        ("/sarpras/loans/{lid}", "detail_peminjaman"),
        ("/sarpras/submissions", "review_pengajuan"),
        ("/sarpras/submissions/{sid}", "detail_pengajuan_sarpras"),
        ("/sarpras/realizations", "antrian_realisasi"),
        ("/sarpras/users", "manajemen_pengguna"),
        ("/sarpras/activity-logs", "riwayat_aktivitas"),
        ("/documents", "dokumen"),
        ("/reports", "laporan"),
        ("/notifications", "notifikasi"),
        ("/profile", "akun_saya"),
    ],
    "kepsek": [
        ("/home", "dashboard"),
        ("/kepala-sekolah/approval", "persetujuan"),
        ("/kepala-sekolah/approval/{aid}", "detail_persetujuan"),
        ("/documents", "dokumen"),
        ("/reports", "laporan"),
        ("/notifications", "notifikasi"),
        ("/profile", "akun_saya"),
    ],
}

# Halaman-halaman sarpras yang dropdown barangnya wajib memakai Select2.
SELECT2_PAGES = {
    "/sarpras/logistics/outgoing": "barang_keluar",
    "/sarpras/logistics/distributions": "distribusi",
    "/sarpras/loans/create": "buat_peminjaman_pilih_barang",
}


class SmokeTest:
    def __init__(self, base_url: str, artifact_dir: Path):
        self.base_url = base_url.rstrip("/")
        self.artifact_dir = artifact_dir
        self.artifact_dir.mkdir(parents=True, exist_ok=True)
        (self.artifact_dir / "screenshots").mkdir(exist_ok=True)
        (self.artifact_dir / "trace").mkdir(exist_ok=True)

        # Seluruh peristiwa console/pageerror, ditandai tag halaman aktif.
        self.events = []
        self.tag = ""

        # Hasil teragregasi.
        self.screenshots = []  # dict perjela
        self.violations = []   # {"severity","message","role","page","viewport"}
        self.gate = LoginGate()

    # ------------------------------------------------------------------
    # Pencatatan hasil
    # ------------------------------------------------------------------
    def fail(self, message, role="", page="", viewport="", severity="FAIL"):
        self.violations.append({
            "severity": severity,
            "message": message,
            "role": role,
            "page": page,
            "viewport": viewport,
        })

    def warn(self, message, role="", page="", viewport=""):
        self.fail(message, role, page, viewport, severity="WARN")

    # ------------------------------------------------------------------
    # Hook konsol & kesalahan halaman
    # ------------------------------------------------------------------
    def hook_context(self, context):
        context.on("console", self._on_console)
        context.on("page", lambda p: p.on("pageerror", self._on_pageerror))

    def _on_console(self, msg):
        if msg.type == "error":
            self.events.append(("console", msg.text, self.tag))

    def _on_pageerror(self, exc):
        self.events.append(("pageerror", str(exc), self.tag))

    # ------------------------------------------------------------------
    # Utilitas navigasi
    # ------------------------------------------------------------------
    def url(self, path: str) -> str:
        return urljoin(self.base_url + "/", path.lstrip("/"))

    def goto(self, page, path: str) -> int:
        """Buka halaman, kosongkan senarai peristiwa, catat status HTTP."""
        self.events.clear()
        self.tag = path
        try:
            resp = page.goto(self.url(path), wait_until="domcontentloaded", timeout=45000)
            try:
                page.wait_for_load_state("networkidle", timeout=20000)
            except PlaywrightTimeoutError:
                self.warn("networkidle tidak tercapai (mungkin aset eksternal lambat)", page=path)
            return resp.status if resp else 200
        except PlaywrightTimeoutError:
            self.fail(f"Waktu habis memuat {path}", page=path)
            return 0

    @staticmethod
    def body_text(page) -> str:
        try:
            return (page.inner_text("body") or "")
        except Exception:
            return ""

    # ------------------------------------------------------------------
    # Pemeriksaan satu halaman (status HTTP direkam pemanggil via goto()).
    # ------------------------------------------------------------------
    def check_page(self, page, role: str, page_name: str, path: str, viewport: str):
        body = self.body_text(page)

        for marker in CRASH_MARKERS:
            if marker in body:
                self.fail(f"Teks tak terduga '{marker}' muncul di halaman", role, page_name, viewport)

        page_errors = [e for e in self.events if e[2] == path and e[0] == "pageerror"]
        for _, text, _ in page_errors:
            self.fail(f"pageerror: {text[:300]}", role, page_name, viewport)

        console_errors = [e for e in self.events if e[2] == path and e[0] == "console"]
        for _, text, _ in console_errors:
            # Gagal memuat sub-sumber daya (font/iklan eksternal) dilaporkan
            # browser sebagai console error; jadikan WARN, bukan kegagalan.
            if re.search(r"net::ERR|Failed to load resource", text, re.IGNORECASE):
                self.warn(f"console.error (resource): {text[:220]}", role, page_name, viewport)
            else:
                self.fail(f"console.error: {text[:300]}", role, page_name, viewport)

        try:
            dims = page.evaluate("""() => {
                const d = document.documentElement;
                return { sw: d.scrollWidth, cw: d.clientWidth };
            }""")
            if dims["sw"] - dims["cw"] > 2:
                self.fail(
                    f"Overflow horizontal: scrollWidth={dims['sw']} > clientWidth={dims['cw']}",
                    role, page_name, viewport,
                )
        except Exception as exc:
            self.fail(f"Gagal mengukur overflow: {exc}", role, page_name, viewport)

    # ------------------------------------------------------------------
    # Screenshot ke direktori artifact.
    # ------------------------------------------------------------------
    def screenshot(self, page, role: str, page_name: str, viewport: str):
        safe = re.sub(r"[^a-z0-9_]+", "-", f"{role}_{page_name}_{viewport}".lower())
        filename = f"screenshots/{safe}.png"
        path = self.artifact_dir / filename
        page.screenshot(path=str(path), full_page=True)
        self.screenshots.append({
            "file": filename,
            "role": role,
            "page": page_name,
            "viewport": viewport,
        })
        return path

    # ------------------------------------------------------------------
    # Login / logout
    # ------------------------------------------------------------------
    def login(self, page, username: str, password: str = "password123"):
        self.gate.wait_slot()
        page.goto(self.url("/login"), wait_until="domcontentloaded", timeout=45000)
        page.fill("#login", username)
        page.fill("#password", password)
        with page.expect_navigation(wait_until="domcontentloaded", timeout=45000):
            page.click("button[type=submit]")
        page.wait_for_load_state("networkidle", timeout=20000)
        if "/login" in page.url:
            self.fail(f"Login gagal untuk {username}: tetap di halaman login", role=username, page="login")
            return False
        return True

    def logout(self, page):
        self.events.clear()
        try:
            page.locator('form[action*="/logout"] button[type=submit]').first.click(
                timeout=10000
            )
        except Exception:
            return
        page.wait_for_load_state("networkidle", timeout=20000)

    # ------------------------------------------------------------------
    # Resolusi id dari daftar
    # ------------------------------------------------------------------
    def detail_ids(self, page, patterns) -> dict:
        """Baca href daftar pertama (per pola) lewat evaluasi langsung."""
        body = page.content()
        found = {}
        for key, pattern in patterns.items():
            m = re.search(pattern, body)
            if m:
                found[key] = m.group("id")
            else:
                self.warn(f"Tidak menemukan link detail {key} (pola {pattern}) — halaman detail dilewati", page="resolusi")
        return found

    def build_path(self, path: str, ids: dict) -> str:
        for key, value in ids.items():
            path = path.replace("{" + key + "}", value)
        return path

    # ------------------------------------------------------------------
    # Penelusuran halaman per role
    # ------------------------------------------------------------------
    def run_traversal(self, browser):
        for role, pages in ROLE_PAGES.items():
            context = browser.new_context()
            self.hook_context(context)
            page = context.new_page()
            if not self.login(page, role):
                context.close()
                continue

            # Siapkan kumpulan id detail dari halaman daftar masing-masing.
            ids = {}
            if role == "kajur_rpl":
                page.goto(self.url("/kajur/submissions"), wait_until="domcontentloaded", timeout=45000)
                page.wait_for_load_state("networkidle", timeout=20000)
                ids.update(self.detail_ids(page, {
                    "sid": r'href="[^"]*?/kajur/submissions/(?P<id>\d+)"',
                }))
                page.goto(self.url("/kajur/inventory"), wait_until="domcontentloaded", timeout=45000)
                page.wait_for_load_state("networkidle", timeout=20000)
                ids.update(self.detail_ids(page, {
                    "iid": r'href="[^"]*?/kajur/inventory/(?P<id>\d+)"',
                }))
            elif role == "sarpras":
                page.goto(self.url("/sarpras/submissions"), wait_until="domcontentloaded", timeout=45000)
                page.wait_for_load_state("networkidle", timeout=20000)
                ids.update(self.detail_ids(page, {
                    "sid": r'href="[^"]*?/sarpras/submissions/(?P<id>\d+)"',
                }))
                page.goto(self.url("/sarpras/inventory"), wait_until="domcontentloaded", timeout=45000)
                page.wait_for_load_state("networkidle", timeout=20000)
                ids.update(self.detail_ids(page, {
                    "iid": r'href="[^"]*?/sarpras/inventory/(?P<id>\d+)"',
                }))
                page.goto(self.url("/sarpras/loans"), wait_until="domcontentloaded", timeout=45000)
                page.wait_for_load_state("networkidle", timeout=20000)
                ids.update(self.detail_ids(page, {
                    "lid": r'href="[^"]*?/sarpras/loans/(?P<id>\d+)"',
                }))
            else:  # kepsek
                page.goto(self.url("/kepala-sekolah/approval"), wait_until="domcontentloaded", timeout=45000)
                page.wait_for_load_state("networkidle", timeout=20000)
                ids.update(self.detail_ids(page, {
                    "aid": r'href="[^"]*?/kepala-sekolah/approval/(?P<id>\d+)"',
                }))

            for path, name in pages:
                full = self.build_path(path, ids)
                if "{" in full:
                    self.warn(f"Halaman detail tidak dapat di-resolusi: {path} — dilewati", role, name, "")
                    continue
                for width, height in VIEWPORTS:
                    page.set_viewport_size({"width": width, "height": height})
                    vp_label = f"{width}x{height}"
                    status = self.goto(page, full)
                    self.check_page(page, role, name, full, vp_label)
                    if status != 200:
                        self.fail(f"Status HTTP {status} untuk {full}", role, name, vp_label)
                    self.screenshot(page, role, name, vp_label)

                # Verifikasi Select2 pada dropdown barang tertentu.
                if role == "sarpras" and full in SELECT2_PAGES:
                    page.set_viewport_size({"width": 1366, "height": 768})
                    self.goto(page, full)
                    count = page.locator(".select2-container").count()
                    if count == 0:
                        self.fail(
                            f"Tidak ada .select2-container pada dropdown barang "
                            f"({SELECT2_PAGES[full]}) — Select2 tidak terinisialisasi",
                            role, name, "1366x768",
                        )

            self.logout(page)
            context.close()

    # ------------------------------------------------------------------
    # Skenario interaktif alur pengajuan -> realisasi
    # ------------------------------------------------------------------
    def run_interactive(self, browser):
        ts = datetime.now().strftime("%Y%m%d-%H%M%S")
        title = f"Permintaan Browser Smoke {ts}"

        # ---- 1. Kajur: buat dan ajukan pengajuan.
        ctx_kajur = browser.new_context()
        self.hook_context(ctx_kajur)
        page = ctx_kajur.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "kajur_rpl"):
            ctx_kajur.close()
            return

        page.goto(self.url("/kajur/submissions/create"), wait_until="domcontentloaded", timeout=45000)
        page.wait_for_load_state("networkidle", timeout=20000)
        page.fill('input[name="title"]', title)
        page.fill('textarea[name="purpose"]', "Dibuat otomatis oleh browser smoke CI.")
        page.fill('input[name="items[0][item_name]"]', "Laptop Bare Metal Smoke")
        page.fill('input[name="items[0][quantity]"]', "1")
        page.fill('input[name="items[0][unit]"]', "Unit")

        self.events.clear()
        self.tag = "kajur submit"
        with page.expect_navigation(wait_until="domcontentloaded", timeout=45000):
            page.click('button[name="action"][value="submit"]')
        page.wait_for_load_state("networkidle", timeout=20000)
        self.screenshot(page, "alur", "kajur_pengajuan_terkirim", "1366x768")

        if "berhasil disimpan" not in self.body_text(page):
            self.fail("Flash sukses pengajuan tidak muncul setelah submit", "alur", "kajur submit", "")
        row_link = page.locator(f'tr:has-text("{title}") a[href*="/kajur/submissions/"]')
        if row_link.count() == 0:
            self.fail("Tidak menemukan baris pengajuan baru di daftar Kajur", "alur", "kajur submit", "")
            ctx_kajur.close()
            return
        href = row_link.first.get_attribute("href")
        m = re.search(r'/kajur/submissions/(\d+)', href)
        if not m:
            self.fail("Link pengajuan baru tidak memiliki id", "alur", "kajur submit", "")
            ctx_kajur.close()
            return
        submission_id = m.group(1)
        submission_path = f"/kajur/submissions/{submission_id}"
        ctx_kajur.close()

        # ---- 2. Sarpras: lonceng notifikasi + tinjau pengajuan.
        ctx_sarpras = browser.new_context()
        self.hook_context(ctx_sarpras)
        page = ctx_sarpras.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "sarpras"):
            ctx_sarpras.close()
            return

        page.goto(self.url("/home"), wait_until="domcontentloaded", timeout=45000)
        page.wait_for_load_state("networkidle", timeout=20000)
        bell = page.locator('button[aria-label="Notifikasi"]')
        badge = bell.locator('.badge.badge-circle.badge-danger')
        unread_before = badge.is_visible() and badge.inner_text().strip() or "0"
        if unread_before in ("", "0"):
            self.warn("Badge belum dibaca tidak tampil (mungkin notifikasi sudah dibaca)", "sarpras", "lonceng", "")

        # Buka dropdown lonceng; verifikasi menu terbuka dan berisi link.
        bell.click()
        page.wait_for_timeout(600)
        dropdown = page.locator(".menu-sub-dropdown")
        if not dropdown.is_visible():
            self.fail("Dropdown notifikasi lonceng tidak terbuka", "sarpras", "lonceng", "")
        links = dropdown.locator('a[href*="/notifications/"]')
        if links.count() == 0:
            self.fail("Tidak ada notifikasi pengajuan di dropdown lonceng", "sarpras", "lonceng", "")
        self.screenshot(page, "alur", "sarpras_lonceng_terbuka", "1366x768")

        # Klik notifikasi pertama -> harus buka pengajuan terkait + tandai dibaca.
        first_notif_href = links.first.get_attribute("href")
        links.first.click()
        page.wait_for_load_state("networkidle", timeout=20000)
        self.screenshot(page, "alur", "sarpras_notifikasi_terbuka", "1366x768")
        if "/sarpras/submissions/" not in page.url:
            self.fail(f"Klik notifikasi tidak membuka pengajuan (URL: {page.url})", "sarpras", "lonceng", "")
        # Kembali ke home, pastikan badge belum dibaca turun.
        page.goto(self.url("/home"), wait_until="domcontentloaded", timeout=45000)
        page.wait_for_load_state("networkidle", timeout=20000)
        badge_after = page.locator('button[aria-label="Notifikasi"] .badge.badge-circle.badge-danger')
        try:
            after_text = badge_after.inner_text().strip() if badge_after.is_visible() else "0"
        except Exception:
            after_text = "0"
        if unread_before not in ("", "0") and after_text and unread_before == after_text:
            self.warn("Klik notifikasi belum menurunkan jumlah belum dibaca", "sarpras", "lonceng", "")

        # Tinjau pengajuan (review -> Kepsek).
        page.goto(self.url(f"/sarpras/submissions/{submission_id}"), wait_until="domcontentloaded", timeout=45000)
        page.wait_for_load_state("networkidle", timeout=20000)
        page.fill("#sarprasNotes", "Telaah browser smoke: kebutuhan sesuai, diteruskan ke Kepala Sekolah.")
        self.screenshot(page, "alur", "sarpras_form_review", "1366x768")
        page.once("dialog", lambda d: d.accept())
        with page.expect_navigation(wait_until="domcontentloaded", timeout=45000):
            page.click('button[name="action"][value="review"]')
        page.wait_for_load_state("networkidle", timeout=20000)
        if "berhasil diproses" not in self.body_text(page):
            self.fail("Tidak memperoleh flash sukses verifikasi Sarpras", "sarpras", "review", "")
        self.screenshot(page, "alur", "sarpras_review_selesai", "1366x768")
        ctx_sarpras.close()

        # ---- 3. Kepsek: setujui pengajuan.
        ctx_kepsek = browser.new_context()
        self.hook_context(ctx_kepsek)
        page = ctx_kepsek.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "kepsek"):
            ctx_kepsek.close()
            return

        page.goto(self.url("/kepala-sekolah/approval"), wait_until="domcontentloaded", timeout=45000)
        page.wait_for_load_state("networkidle", timeout=20000)
        # Cari baris pengajuan saya: link detail pada baris yang memuat judul unik.
        row = page.locator(f'tr:has-text("{title}") a[href*="/kepala-sekolah/approval/"]')
        if row.count() == 0:
            self.fail("Pengajuan tidak ditemukan di daftar persetujuan Kepsek", "kepsek", "approval", "")
            ctx_kepsek.close()
            return
        row.first.click()
        page.wait_for_load_state("networkidle", timeout=20000)
        page.fill("#principalNotes", "Browser smoke: disetujui untuk direalisasikan.")
        self.screenshot(page, "alur", "kepsek_form_approve", "1366x768")
        page.once("dialog", lambda d: d.accept())
        with page.expect_navigation(wait_until="domcontentloaded", timeout=45000):
            page.click('button[name="action"][value="approve"]')
        page.wait_for_load_state("networkidle", timeout=20000)
        if "DISETUJUI" not in self.body_text(page) and "Disetujui" not in self.body_text(page):
            self.fail("Status 'Disetujui' tidak tampil setelah approval Kepsek", "kepsek", "approve", "")
        self.screenshot(page, "alur", "kepsek_approve_selesai", "1366x768")
        ctx_kepsek.close()

        # ---- 4. Sarpras: realisasi (toggle mode + Select2 + kategori kosong).
        ctx_real = browser.new_context()
        self.hook_context(ctx_real)
        ctx_real.tracing.start(screenshots=True, snapshots=True, sources=False)
        page = ctx_real.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "sarpras"):
            ctx_real.stop_tracing()

            ctx_real.close()
            return

        page.goto(self.url("/sarpras/realizations"), wait_until="domcontentloaded", timeout=45000)
        page.wait_for_load_state("networkidle", timeout=20000)
        row = page.locator(f"tr:has-text('{title}')")
        realize_link = row.locator('a[href$="/realize"]')
        if realize_link.count() == 0:
            self.fail("Pengajuan disetujui tidak ditemukan di antrean realisasi", "sarpras", "realisasi", "")
            ctx_real.stop_tracing()
            ctx_real.close()
            return
        realize_link.first.click()
        page.wait_for_load_state("networkidle", timeout=20000)

        if "/realize" not in page.url:
            self.fail(f"Form realisasi tidak terbuka (URL: {page.url})", "sarpras", "realisasi", "")

        # Select2 sudah terinisialisasi pada dropdown item inventaris.
        if page.locator(".select2-container").count() == 0:
            self.fail("Tidak ada .select2-container di form realisasi", "sarpras", "realisasi", "")

        # Toggle mode tiap baris ke existing lalu pilih via Select2, kembali new.
        for idx in range(page.locator(".mode-select").count()):
            mode = page.locator(f'select[name="lines[{idx}][mode]"]')
            item_select = page.locator(f'select[name="lines[{idx}][item_id]"]')
            item_col = item_select.locator("xpath=ancestor::div[contains(@class,'col')]")

            # existing -> kolom pilih barang tampil.
            mode.select_option("existing")
            page.wait_for_timeout(200)
            mode_value = mode.input_value()
            if mode_value != "existing":
                self.fail(f"Mode baris {idx} tidak menjadi existing", "sarpras", "realisasi", "")
            if item_col.is_visible():
                container = page.locator(
                    f'select[name="lines[{idx}][item_id]"] + .select2-container'
                ).first
                try:
                    self.events.clear()
                    self.tag = "realisasi select2"
                    container.click(timeout=8000)
                    page.wait_for_timeout(300)
                    option = page.locator(
                        '.select2-container--open .select2-results__option:not([aria-disabled="true"])'
                    ).first
                    option.click(timeout=8000)
                    page.wait_for_timeout(200)
                except PlaywrightTimeoutError:
                    self.fail(f"Select2 baris {idx} tidak bisa dibuka/dipilih", "sarpras", "realisasi", "")

            # kembali new -> panel barang baru tampil.
            mode.select_option("new")
            page.wait_for_timeout(200)
            new_panel = page.locator(f'input[name="lines[{idx}][new_name]"]').locator(
                "xpath=ancestor::div[contains(@class,'new-item-panel')]"
            )
            if not new_panel.is_visible():
                self.fail(f"Panel barang baru baris {idx} tidak tampil setelah toggle ke new", "sarpras", "realisasi", "")

            # Nama/unit/kondisi pakai bawaan; kategori sengaja dikosongkan.
            name_input = page.locator(f'input[name="lines[{idx}][new_name]"]')
            if not name_input.input_value().strip():
                name_input.fill(f"Laptop Bare Metal Smoke {idx}")

        page.select_option('select[name="source"]', "pembelian")
        page.fill('input[name="realization_notes"]', "Realisasi browser smoke (kategori kosong -> Umum).")
        self.screenshot(page, "alur", "sarpras_form_realisasi", "1366x768")

        self.events.clear()
        self.tag = "realisasi submit"
        with page.expect_navigation(wait_until="domcontentloaded", timeout=60000):
            page.click('#realization-form button[type="submit"]')
        page.wait_for_load_state("networkidle", timeout=20000)
        self.screenshot(page, "alur", "sarpras_realisasi_selesai", "1366x768")

        if "berhasil direalisasikan" not in self.body_text(page):
            self.fail("Flash sukses realisasi tidak muncul", "sarpras", "realisasi", "")
        if "Terealisasi" not in self.body_text(page):
            self.fail("Status 'Terealisasi' tidak tampil setelah realisasi", "sarpras", "realisasi", "")

        ctx_real.tracing.stop(path=str(self.artifact_dir / "trace" / "alur-realisasi.zip"))
        ctx_real.close()

    # ------------------------------------------------------------------
    # Halaman error khusus + throttle login
    # ------------------------------------------------------------------
    def run_special_pages(self, browser):
        ctx = browser.new_context()
        self.hook_context(ctx)
        page = ctx.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "sarpras"):
            ctx.close()
            return

        # 404 khusus.
        status = self.goto(page, "/halaman-tidak-ada-404-smoke")
        body = self.body_text(page)
        if status != 404:
            self.fail(f"Harusnya 404, dapat {status}", "sarpras", "404", "")
        if "Halaman Tidak Ditemukan" not in body:
            self.fail("View 404 khusus tidak dirender", "sarpras", "404", "")
        self.screenshot(page, "error", "halaman_404", "1366x768")

        # 403 khusus (kajur route, bukan wewenang sarpras).
        status = self.goto(page, "/kajur/submissions")
        body = self.body_text(page)
        if status != 403:
            self.fail(f"Harusnya 403, dapat {status}", "sarpras", "403", "")
        if "Akses Ditolak" not in body:
            self.fail("View 403 khusus tidak dirender", "sarpras", "403", "")
        self.screenshot(page, "error", "halaman_403", "1366x768")

        self.logout(page)
        ctx.close()

        # Throttle login: 6 percobaan salah -> pesan throttle di form login.
        ctx_throttle = browser.new_context()
        self.hook_context(ctx_throttle)
        page = ctx_throttle.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        page.goto(self.url("/login"), wait_until="domcontentloaded", timeout=45000)
        throttle_message = False
        for attempt in range(6):
            page.fill("#login", "user_throttle_smoke")
            page.fill("#password", "salah-salah")
            try:
                with page.expect_navigation(wait_until="domcontentloaded", timeout=30000):
                    page.click("button[type=submit]")
                page.wait_for_load_state("networkidle", timeout=15000)
            except PlaywrightTimeoutError:
                break
            if "Terlalu banyak percobaan" in self.body_text(page):
                throttle_message = True
                break
        if not throttle_message:
            self.fail("Pesan throttle tidak tampil di form login setelah 6 percobaan", "login", "throttle", "")
        self.screenshot(page, "error", "login_throttle", "1366x768")
        ctx_throttle.close()

    # ------------------------------------------------------------------
    # Indeks & laporan akhir
    # ------------------------------------------------------------------
    def write_index(self, exit_code: int):
        rows = []
        rows.append("<html><head><meta charset='utf-8'></head><body>")
        rows.append(f"<h1>Browser Smoke SIILS</h1>")
        rows.append(f"<p>Dibuat {datetime.now().isoformat()} • base-url {self.base_url}</p>")
        rows.append(f"<h2>Hasil</h2>")
        rows.append(f"<p>Exit code: <b>{exit_code}</b> • violations: {len(self.violations)}</p>")

        rows.append("<h2>Violations</h2>")
        if self.violations:
            rows.append("<table border='1' cellpadding='4' cellspacing='0'>")
            rows.append("<tr><th>Severity</th><th>Role</th><th>Halaman</th><th>Viewport</th><th>Pesan</th></tr>")
            for v in self.violations:
                rows.append(
                    f"<tr><td>{v['severity']}</td><td>{v['role']}</td><td>{v['page']}</td>"
                    f"<td>{v['viewport']}</td><td>{v['message']}</td></tr>"
                )
            rows.append("</table>")
        else:
            rows.append("<p>Tidak ada pelanggaran.</p>")

        rows.append("<h2>Screenshot</h2>")
        rows.append("<table border='1' cellpadding='4' cellspacing='0'>")
        rows.append("<tr><th>Role</th><th>Halaman</th><th>Viewport</th><th>Berkas</th><th>Gambar</th></tr>")
        for s in self.screenshots:
            rows.append(
                f"<tr><td>{s['role']}</td><td>{s['page']}</td><td>{s['viewport']}</td>"
                f"<td>{s['file']}</td><td><a href='{s['file']}'><img src='{s['file']}' width='320'></a></td></tr>"
            )
        rows.append("</table>")
        rows.append("</body></html>")
        (self.artifact_dir / "index.html").write_text("\n".join(rows), encoding="utf-8")

        (self.artifact_dir / "report.json").write_text(
            json.dumps({
                "exit_code": exit_code,
                "violations": self.violations,
                "screenshots": self.screenshots,
            }, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default=os.environ.get("APP_URL", "http://127.0.0.1:8000"))
    parser.add_argument("--artifact-dir", default=str(Path("storage/logs/browser-smoke")))
    parser.add_argument("--headless", dest="headless", action="store_true", default=True)
    parser.add_argument("--no-headless", dest="headless", action="store_false")
    parser.add_argument("--wait-server", type=int, default=90, help="detik menunggu server siap")
    args = parser.parse_args(argv)

    artifact_dir = Path(args.artifact_dir).resolve()
    smoke = SmokeTest(args.base_url, artifact_dir)

    # Tunggu server siap.
    import urllib.request

    ready = False
    deadline = time.time() + args.wait_server
    while time.time() < deadline:
        try:
            with urllib.request.urlopen(smoke.url("/up"), timeout=5) as resp:
                if resp.status == 200:
                    ready = True
                    break
        except Exception:
            pass
        time.sleep(3)
    if not ready:
        print(f"Server {smoke.base_url} tidak siap dalam {args.wait_server}s", file=sys.stderr)
        smoke.fail("Server aplikasi tidak siap", page="bootstrap")
        smoke.write_index(1)
        return 1

    exit_code = 0
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=args.headless, args=["--no-sandbox"])
        try:
            smoke.run_traversal(browser)
            smoke.run_interactive(browser)
            smoke.run_special_pages(browser)
        except Exception as exc:
            smoke.fail(f"Eksekusi gagal: {exc}")
            exit_code = 1
        finally:
            browser.close()

    # Bundel peristiwa konsol untuk dipakai bila ada kegagalan referensi.
    if any(v["severity"] == "FAIL" for v in smoke.violations):
        exit_code = 1
    smoke.write_index(exit_code)

    print(f"\n{len(smoke.violations)} pelanggaran, {len(smoke.screenshots)} screenshot.")
    for v in smoke.violations:
        print(f"  [{v['severity']}] {v['role']}/{v['page']} {v['viewport']}: {v['message']}")
    return exit_code


if __name__ == "__main__":
    sys.exit(main())