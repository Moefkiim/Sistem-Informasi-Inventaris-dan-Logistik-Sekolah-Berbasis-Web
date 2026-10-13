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
import csv
import io
import json
import os
import re
import sys
import time
import zipfile
from datetime import datetime
from pathlib import Path
from urllib.parse import urljoin

from playwright.sync_api import (
    TimeoutError as PlaywrightTimeoutError,
    sync_playwright,
)

try:  # hanya tersedia di CI (paket pip opsional).
    from axe_playwright_python.sync_playwright import Axe
except Exception:  # pragma: no cover - lingkungan lokal tanpa paket axe.
    Axe = None

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

# ---------------------------------------------------------------------------
# Jenis laporan yang wajib ditelusuri untuk tiap role (filter scoping otomatis).
# ---------------------------------------------------------------------------
REPORT_TYPES = [
    "inventory",
    "incoming",
    "outgoing",
    "distribution",
    "submission",
    "loan",
    "condition",
]

# Tingkat dampak axe-core yang memblokir job (harus nol, kecuali pada allowlist).
BLOCKING_IMPACTS = ("serious", "critical")

# Tahap 1 aksesibilitas dibatasi pada halaman laporan. Ubah ke True untuk
# memperluas axe-core ke seluruh halaman yang ditelusuri.
A11Y_FULL_TRAVERSAL = False

# Temuan serious/critical wajib berjumlah nol (selain yang didaftarkan di
# a11y-allowlist.json). Aktifkan kembali ke True hanya jika suite memang
# sedang mendata temuan lama.
A11Y_ENFORCE = True

# Berkas pengecualian aksesibilitas (hanya diperbolehkan dengan alasan tertulis).
A11Y_ALLOWLIST_PATH = Path(__file__).with_name("a11y-allowlist.json")

# ---------------------------------------------------------------------------
# Halaman-halaman sarpras yang dropdown barangnya wajib memakai Select2.
# ---------------------------------------------------------------------------
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
        (self.artifact_dir / "downloads").mkdir(exist_ok=True)
        self.a11y_violations = []
        self.axe = None
        self._axe_warned = False

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
                let culprits = [];
                for (const el of document.querySelectorAll('body *')) {
                    if (el.getBoundingClientRect().right > d.clientWidth + 2) {
                        const r = el.getBoundingClientRect();
                        culprits.push(`${el.tagName}.${String(el.className).slice(0,60)} (left=${Math.round(r.left)}, width=${Math.round(r.width)})`);
                        if (culprits.length >= 8) break;
                    }
                }
                return { sw: d.scrollWidth, cw: d.clientWidth, culprits };
            }""")
            if dims["sw"] - dims["cw"] > 2:
                self.fail(
                    f"Overflow horizontal: scrollWidth={dims['sw']} > clientWidth={dims['cw']}; tersangka: {dims['culprits']}",
                    role, page_name, viewport,
                )
        except Exception as exc:
            self.fail(f"Gagal mengukur overflow: {exc}", role, page_name, viewport)

        self.run_a11y(page, role, page_name, viewport)

    # ------------------------------------------------------------------
    # Aksesibilitas (axe-core) — dijalankan pada setiap halaman & viewport.
    # ------------------------------------------------------------------
    def run_a11y(self, page, role: str, page_name: str, viewport: str):
        if not A11Y_FULL_TRAVERSAL and not page_name.startswith("laporan_"):
            return

        if Axe is None:
            if not self._axe_warned:
                self.warn("axe-core tidak tersedia (paket axe-playwright-python tidak terpasang) — pemeriksaan aksesibilitas dilewati")
                self._axe_warned = True
            return

        if self.axe is None:
            self.axe = Axe()

        try:
            try:
                results = self.axe.run(page, options={"resultTypes": ["violations"]})
            except TypeError:
                results = self.axe.run(page)
        except Exception as exc:
            self.warn(f"axe-core gagal dijalankan: {exc}", role, page_name, viewport)
            return

        response = results.response or {}
        for violation in response.get("violations", []):
            nodes = violation.get("nodes", [])
            targets = [t for node in nodes for t in node.get("target", [])][:5]
            self.a11y_violations.append({
                "role": role,
                "page": page_name,
                "path": self.tag,
                "viewport": viewport,
                "rule": violation.get("id"),
                "impact": violation.get("impact"),
                "help": violation.get("help"),
                "help_url": violation.get("helpUrl"),
                "nodes": len(nodes),
                "targets": targets,
                "details": self._a11y_node_details(nodes),
            })

    @staticmethod
    def _a11y_node_details(nodes: list) -> list:
        details = []
        for node in nodes[:5]:
            check_data = {}
            for check in node.get("any", []) + node.get("all", []):
                if isinstance(check.get("data"), dict):
                    check_data.update(check["data"])
                    break
            details.append({
                "target": node.get("target"),
                "html": node.get("html"),
                "fgColor": check_data.get("fgColor"),
                "bgColor": check_data.get("bgColor"),
                "contrastRatio": check_data.get("contrastRatio"),
                "expected": check_data.get("expectedContrastRatio"),
                "summary": node.get("failureSummary"),
            })
        return details

    def load_a11y_allowlist(self) -> dict:
        if not A11Y_ALLOWLIST_PATH.exists():
            return {}
        try:
            data = json.loads(A11Y_ALLOWLIST_PATH.read_text(encoding="utf-8"))
        except Exception as exc:
            self.warn(f"Allowlist aksesibilitas tidak dapat dibaca: {exc}")
            return {}
        allowlist = {}
        for entry in data.get("exceptions", []):
            allowlist.setdefault(entry.get("rule"), []).append(entry.get("reason", "(tanpa alasan)"))
        return allowlist

    def check_a11y(self) -> int:
        """Tandai serious/critical yang tidak ada di allowlist sebagai kegagalan."""
        allowlist = self.load_a11y_allowlist()
        seen_blockers = set()
        for v in self.a11y_violations:
            impact = v["impact"]
            rule = v["rule"]
            if impact in BLOCKING_IMPACTS:
                if rule in allowlist:
                    continue
                key = (rule, v["role"], v["page"], v["viewport"])
                if key in seen_blockers:
                    continue
                seen_blockers.add(key)
                detail = (v.get("details") or [{}])[0]
                colors = ""
                if detail.get("fgColor") and detail.get("bgColor"):
                    colors = (
                        f" [{detail['fgColor']} di atas {detail['bgColor']}"
                        f" = {detail.get('contrastRatio')}:1, minimal {detail.get('expected')}]"
                    )
                message = (
                    f"a11y {impact}: {rule} — {v['help']} "
                    f"({v['nodes']} elemen; mis. {v['targets']}){colors}"
                )
                if A11Y_ENFORCE:
                    self.fail(message, v["role"], v["page"], v["viewport"])
                else:
                    self.warn(f"[lapor] {message}", v["role"], v["page"], v["viewport"])
            elif impact == "moderate":
                self.warn(
                    f"a11y moderate: {rule} — {v['help']} ({v['nodes']} elemen)",
                    v["role"], v["page"], v["viewport"],
                )
        return len(self.a11y_violations)

    def write_a11y_report(self):
        grouped = {}
        for v in self.a11y_violations:
            bucket = grouped.setdefault(v["rule"], {
                "rule": v["rule"],
                "impact": v["impact"],
                "help": v["help"],
                "help_url": v["help_url"],
                "count": 0,
                "pages": {},
            })
            bucket["count"] += 1
            page_key = f"{v['role']}/{v['page']} @ {v['viewport']}"
            bucket["pages"].setdefault(page_key, {"nodes": 0, "targets": [], "details": []})
            bucket["pages"][page_key]["nodes"] += v["nodes"]
            bucket["pages"][page_key]["targets"].extend(v["targets"])
            bucket["pages"][page_key]["details"].extend(v.get("details", []))

        summary = {}
        for v in self.a11y_violations:
            summary[v["impact"]] = summary.get(v["impact"], 0) + 1

        payload = {
            "generated_at": datetime.now().isoformat(),
            "total_violations": len(self.a11y_violations),
            "by_impact": summary,
            "by_rule": sorted(grouped.values(), key=lambda r: (-r["count"], r["rule"] or "")),
        }
        (self.artifact_dir / "a11y-report.json").write_text(
            json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8"
        )
        return payload

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
        if "disetujui" not in self.body_text(page).lower():
            body_snippet = " / ".join(self.body_text(page).splitlines()[-12:])[:400]
            self.fail(
                f"Status 'Disetujui' tidak tampil setelah approval Kepsek. URL={page.url}. Akhir badan: {body_snippet}",
                "kepsek", "approve", "",
            )
        self.screenshot(page, "alur", "kepsek_approve_selesai", "1366x768")
        ctx_kepsek.close()

        # ---- 4. Sarpras: realisasi (toggle mode + Select2 + kategori kosong).
        ctx_real = browser.new_context()
        self.hook_context(ctx_real)
        ctx_real.tracing.start(screenshots=True, snapshots=True, sources=False)
        page = ctx_real.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "sarpras"):
            ctx_real.tracing.stop(path=str(self.artifact_dir / "trace" / "alur-realisasi.zip"))

            ctx_real.close()
            return

        page.goto(self.url("/sarpras/realizations"), wait_until="domcontentloaded", timeout=45000)
        page.wait_for_load_state("networkidle", timeout=20000)
        row = page.locator(f"tr:has-text('{title}')")
        realize_link = row.locator('a[href$="/realize"]')
        if realize_link.count() == 0:
            self.fail("Pengajuan disetujui tidak ditemukan di antrean realisasi", "sarpras", "realisasi", "")
            ctx_real.tracing.stop(path=str(self.artifact_dir / "trace" / "alur-realisasi.zip"))
            ctx_real.close()
            return
        realize_link.first.click()
        page.wait_for_load_state("networkidle", timeout=20000)

        if "/realize" not in page.url:
            self.fail(f"Form realisasi tidak terbuka (URL: {page.url})", "sarpras", "realisasi", "")

        # Snapshot keadaan halaman untuk diagnosa bila form tidak dirender.
        page.screenshot(path=str(self.artifact_dir / "screenshots" / "alur_sarpras_form_realisasi_1366x768.png"), full_page=True)
        has_source = page.locator('select[name="source"]').count()
        if not has_source:
            body_snippet = " / ".join(self.body_text(page).splitlines()[-12:])[:500]
            self.fail(
                f"Form realisasi tidak dirender (source select tidak ada). URL={page.url}. Akhir badan: {body_snippet}",
                "sarpras", "realisasi", "",
            )
            ctx_real.tracing.stop(path=str(self.artifact_dir / "trace" / "alur-realisasi.zip"))
            ctx_real.close()
            return

        # Select2 sudah terinisialisasi pada dropdown item inventaris.
        if page.locator(".select2-container").count() == 0:
            self.fail("Tidak ada .select2-container di form realisasi", "sarpras", "realisasi", "")

        # Toggle mode tiap baris ke existing lalu pilih via Select2, kembali new.
        for idx in range(page.locator(".mode-select").count()):
            mode = page.locator(f'select[name="lines[{idx}][mode]"]')
            item_select = page.locator(f'select[name="lines[{idx}][item_id]"]')
            item_col = item_select.locator("xpath=ancestor::div[contains(@class,'col-md-4')]")

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
    # Laporan: telusur semua jenis + verifikasi ekspor PDF/Excel/Cetak
    # ------------------------------------------------------------------
    def run_reports(self, browser):
        for role in ROLE_PAGES:
            context = browser.new_context()
            self.hook_context(context)
            page = context.new_page()
            if not self.login(page, role):
                context.close()
                continue

            for report_type in REPORT_TYPES:
                path = f"/reports?type={report_type}"
                for width, height in VIEWPORTS:
                    page.set_viewport_size({"width": width, "height": height})
                    vp_label = f"{width}x{height}"
                    status = self.goto(page, path)
                    self.check_page(page, role, f"laporan_{report_type}", path, vp_label)
                    if status != 200:
                        self.fail(f"Status HTTP {status} untuk laporan {report_type}", role, f"laporan_{report_type}", vp_label)

                    body = self.body_text(page)
                    has_table = page.locator("table.report-table, table.table-report").count() > 0
                    has_empty = ("Belum ada data" in body) or ("Tidak ada hasil untuk filter ini" in body)
                    if not has_table and not has_empty:
                        self.fail(f"Laporan {report_type} tidak memuat tabel maupun keadaan kosong", role, f"laporan_{report_type}", vp_label)
                    if "$summary" in body or "$totalRows" in body:
                        self.fail(f"Variabel mentah tampil pada laporan {report_type}", role, f"laporan_{report_type}", vp_label)
                    self.screenshot(page, role, f"laporan_{report_type}", vp_label)

                page.set_viewport_size({"width": 1366, "height": 768})
                self.goto(page, path)
                self.check_report_exports(page, context, report_type, role)

            self.logout(page)
            context.close()

    def count_report_rows(self, page, report_type: str):
        """Jumlah baris data laporan di layar (gabungan seluruh halaman paginasi)."""
        total = 0
        for page_no in range(1, 21):
            path = f"/reports?type={report_type}&per_page=50&page={page_no}"
            status = self.goto(page, path)
            if status != 200:
                return None
            rows = page.locator("table.report-table tbody tr, table.table-report tbody tr").count()
            body = self.body_text(page)
            if "Belum ada data" in body or "Tidak ada hasil untuk filter ini" in body:
                break
            total += rows
            if page.locator('a[rel="next"]').count() == 0:
                break
        return total

    @staticmethod
    def xlsx_data_rows(body: bytes):
        """Hitung baris data (tanpa baris judul) pada sheet pertama berkas xlsx."""
        try:
            with zipfile.ZipFile(io.BytesIO(body)) as archive:
                sheets = [n for n in archive.namelist() if re.match(r"xl/worksheets/sheet\d+\.xml$", n)]
                if not sheets:
                    return None
                xml = archive.read(sheets[0]).decode("utf-8", "ignore")
        except Exception:
            return None
        total_rows = len(re.findall(r"<row\b", xml))
        return max(total_rows - 1, 0)

    def check_report_exports(self, page, context, report_type: str, role: str):
        page_name = f"laporan_{report_type}_ekspor"
        expected = self.count_report_rows(page, report_type)

        pdf = context.request.get(self.url(f"/reports/pdf?type={report_type}"))
        if pdf.status != 200:
            self.fail(f"PDF laporan {report_type} status {pdf.status}", role, page_name, "1366x768")
        else:
            ctype = pdf.headers.get("content-type", "")
            body = pdf.body()
            if "pdf" not in ctype.lower():
                self.fail(f"PDF laporan {report_type} content-type tidak sesuai: {ctype}", role, page_name, "1366x768")
            if not body.startswith(b"%PDF"):
                self.fail(f"PDF laporan {report_type} tidak diawali %PDF", role, page_name, "1366x768")

        xls = context.request.get(self.url(f"/reports/excel?type={report_type}"))
        if xls.status != 200:
            self.fail(f"Excel laporan {report_type} status {xls.status}", role, page_name, "1366x768")
        else:
            body = xls.body()
            if not body.startswith(b"PK"):
                self.fail(f"Excel laporan {report_type} bukan berkas xlsx (magic bukan PK)", role, page_name, "1366x768")
            else:
                count = self.xlsx_data_rows(body)
                if count is not None and expected is not None and count != expected:
                    self.fail(
                        f"Jumlah baris Excel {report_type} ({count}) berbeda dari baris di layar ({expected})",
                        role, page_name, "1366x768",
                    )

        status = self.goto(page, f"/reports?type={report_type}&export=print")
        body = self.body_text(page)
        if status != 200:
            self.fail(f"Halaman cetak laporan {report_type} status {status}", role, page_name, "1366x768")
        elif "<table" not in page.content() and "Tidak ada data laporan." not in body:
            self.fail(f"Halaman cetak laporan {report_type} tidak memuat tabel/data", role, page_name, "1366x768")
        self.screenshot(page, role, f"laporan_{report_type}_cetak", "1366x768")

    # ------------------------------------------------------------------
    # Skenario: aksi status unit pada barang individual
    # ------------------------------------------------------------------
    def run_status_unit(self, browser):
        context = browser.new_context()
        self.hook_context(context)
        page = context.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "sarpras"):
            context.close()
            return

        self.goto(page, "/sarpras/inventory")
        raw = page.eval_on_selector_all(
            'a[href*="/sarpras/inventory/"]',
            "els => els.map(e => e.getAttribute('href'))",
        )
        detail = None
        for href in dict.fromkeys(raw):
            if not href or not re.search(r"/sarpras/inventory/\d+$", href):
                continue
            path = re.sub(r"^https?://[^/]+", "", href)
            if self.goto(page, path) == 200 and page.locator(".unit-status-action").count() > 0:
                detail = path
                break

        if detail is None:
            self.warn("Tidak ada barang individual dengan aksi status unit — skenario status dilewati", "sarpras", "status-unit", "")
            context.close()
            return

        before = page.locator("#tab_status tbody tr:not(:has-text('Belum ada riwayat'))").count()
        page.locator(".unit-status-action").first.click()
        page.wait_for_timeout(500)
        modal = page.locator("#statusUnitModal")
        if not modal.is_visible():
            self.fail("Modal ubah status unit tidak terbuka", "sarpras", "status-unit", "")
            context.close()
            return
        self.screenshot(page, "sarpras", "status_unit_modal", "1366x768")

        reason = page.locator("#status-reason")
        if not reason.get_attribute("required"):
            self.fail("Field alasan pada modal status tidak bertanda wajib", "sarpras", "status-unit", "")

        url_before = page.url
        age_before = page.locator("#statusUnitModal button[type=submit]").count()
        if age_before:
            page.locator("#statusUnitModal button[type=submit]").click()
            page.wait_for_timeout(400)
            if page.url != url_before:
                self.fail("Modal status tersubmit meski alasan dikosongkan", "sarpras", "status-unit", "")

        reason.fill("Diperiksa otomatis oleh browser smoke CI.")

        if page.locator("#status-condition-wrap:not(.d-none)").count() > 0:
            page.locator("#status-result-condition").select_option(index=0)
        if page.locator('#status-confirm-wrap:not(.d-none) input[name="confirmed"]').count() > 0:
            page.locator("#status-confirm").check()

        self.events.clear()
        self.tag = "status-unit submit"
        with page.expect_navigation(wait_until="domcontentloaded", timeout=45000):
            page.locator("#statusUnitModal button[type=submit]").click()
        page.wait_for_load_state("networkidle", timeout=20000)

        if "berhasil" not in self.body_text(page).lower():
            self.warn("Flash sukses perubahan status unit tidak tampil", "sarpras", "status-unit", "")

        after = page.locator("#tab_status tbody tr:not(:has-text('Belum ada riwayat'))").count()
        if after <= before:
            self.fail(f"Riwayat status unit tidak bertambah (sebelum {before}, sesudah {after})", "sarpras", "status-unit", "")
        self.screenshot(page, "sarpras", "status_unit_riwayat", "1366x768")

        # Unit yang dapat dipinjam tetap muncul pada dropdown peminjaman.
        self.goto(page, "/sarpras/loans/create")
        if page.locator(".select2-container").count() == 0:
            self.warn("Dropdown peminjaman tidak memakai Select2 setelah perubahan status", "sarpras", "status-unit", "")
        context.close()

    # ------------------------------------------------------------------
    # Skenario: peminjaman terlambat pada halaman detail (tanpa buka daftar)
    # ------------------------------------------------------------------
    def run_overdue_loan(self, browser):
        discover = browser.new_context()
        self.hook_context(discover)
        page = discover.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "sarpras"):
            discover.close()
            return
        self.goto(page, "/sarpras/loans?due=overdue")
        link = page.locator('tr:has-text("Terlambat") a[href*="/sarpras/loans/"]').first
        if link.count() == 0:
            self.warn("Tidak ada peminjaman terlambat pada data demo — skenario dilewati", "sarpras", "peminjaman-terlambat", "")
            discover.close()
            return
        path = re.sub(r"^https?://[^/]+", "", link.get_attribute("href") or "")
        discover.close()

        context = browser.new_context()
        self.hook_context(context)
        page = context.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "sarpras"):
            context.close()
            return
        status = self.goto(page, path)
        if status != 200:
            self.fail(f"Detail peminjaman {path} status {status}", "sarpras", "peminjaman-terlambat", "")
        body = self.body_text(page)
        if "Terlambat" not in body:
            self.fail("Status 'Terlambat' tidak tampil pada detail peminjaman", "sarpras", "peminjaman-terlambat", "")
        self.screenshot(page, "sarpras", "peminjaman_terlambat_detail", "1366x768")
        context.close()

    # ------------------------------------------------------------------
    # Skenario: alur impor inventaris (template, pratinjau, konfirmasi)
    # ------------------------------------------------------------------
    def run_import_flow(self, browser):
        context = browser.new_context(accept_downloads=True)
        self.hook_context(context)
        page = context.new_page()
        page.set_viewport_size({"width": 1366, "height": 768})
        if not self.login(page, "sarpras"):
            context.close()
            return

        status = self.goto(page, "/sarpras/inventory/import")
        if status != 200:
            self.fail(f"Halaman impor status {status}", "sarpras", "impor", "")
        if page.locator('input[name="file"]').count() == 0:
            self.fail("Form impor tidak menampilkan input berkas", "sarpras", "impor", "")
        self.screenshot(page, "sarpras", "impor_unggah", "1366x768")

        # Unduh template resmi.
        try:
            with page.expect_download(timeout=30000) as dl:
                page.click('a[href*="/sarpras/inventory/import/template"]')
            download = dl.value
            target = self.artifact_dir / "downloads" / (download.suggested_filename or "template.xlsx")
            download.save_as(str(target))
            if target.read_bytes()[:2] != b"PK":
                self.fail("Template impor bukan berkas xlsx", "sarpras", "impor", "")
        except PlaywrightTimeoutError:
            self.fail("Unduhan template impor tidak dimulai", "sarpras", "impor", "")

        # Berkas salah -> pratinjau menampilkan kesalahan, konfirmasi nonaktif.
        bad = self.artifact_dir / "downloads" / "impor_salah.csv"
        with bad.open("w", newline="", encoding="utf-8") as fh:
            writer = csv.writer(fh)
            writer.writerow(["Nama Barang", "Tipe"])
            writer.writerow(["", "salah-tipe"])
        page.set_input_files('input[name="file"]', str(bad))
        with page.expect_navigation(wait_until="domcontentloaded", timeout=45000):
            page.click('form[action*="import/preview"] button[type="submit"]')
        page.wait_for_load_state("networkidle", timeout=20000)
        confirm = page.locator('form[action*="import/confirm"] button[type="submit"]')
        if confirm.count() == 0:
            self.fail("Halaman pratinjau impor tidak tampil untuk berkas salah", "sarpras", "impor", "")
        elif not confirm.is_disabled():
            self.fail("Tombol konfirmasi tetap aktif walau berkas memuat kesalahan", "sarpras", "impor", "")
        self.screenshot(page, "sarpras", "impor_pratinjau_salah", "1366x768")

        # Berkas benar -> pratinjau bersih -> konfirmasi -> barang muncul.
        marker = f"ImporSmoke{datetime.now().strftime('%H%M%S')}"
        good = self.artifact_dir / "downloads" / "impor_benar.csv"
        with good.open("w", newline="", encoding="utf-8") as fh:
            writer = csv.writer(fh)
            writer.writerow(["Nama Barang", "Tipe"])
            writer.writerow([marker, "individual"])
        self.goto(page, "/sarpras/inventory/import")
        page.set_input_files('input[name="file"]', str(good))
        with page.expect_navigation(wait_until="domcontentloaded", timeout=45000):
            page.click('form[action*="import/preview"] button[type="submit"]')
        page.wait_for_load_state("networkidle", timeout=20000)

        confirm = page.locator('form[action*="import/confirm"] button[type="submit"]')
        if confirm.count() == 0 or confirm.is_disabled():
            self.fail("Tombol konfirmasi impor tidak aktif untuk berkas valid", "sarpras", "impor", "")
            context.close()
            return
        self.screenshot(page, "sarpras", "impor_pratinjau_benar", "1366x768")

        self.events.clear()
        self.tag = "impor konfirmasi"
        with page.expect_navigation(wait_until="domcontentloaded", timeout=45000):
            confirm.click()
        page.wait_for_load_state("networkidle", timeout=20000)
        if "berhasil" not in self.body_text(page).lower():
            self.warn("Flash sukses impor tidak tampil", "sarpras", "impor", "")

        self.goto(page, f"/sarpras/inventory?search={marker}")
        if marker not in self.body_text(page):
            self.warn(f"Barang hasil impor '{marker}' tidak terlihat pada pencarian inventaris", "sarpras", "impor", "")
        context.close()

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
        a11y = self.write_a11y_report()
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

        rows.append("<h2>Aksesibilitas (axe-core)</h2>")
        rows.append(f"<p>Total temuan: {a11y['total_violations']} • per dampak: {a11y['by_impact']}</p>")
        if a11y["by_rule"]:
            rows.append("<table border='1' cellpadding='4' cellspacing='0'>")
            rows.append("<tr><th>Rule</th><th>Dampak</th><th>Jumlah</th><th>Bantuan</th></tr>")
            for rule in a11y["by_rule"]:
                rows.append(
                    f"<tr><td>{rule['rule']}</td><td>{rule['impact']}</td>"
                    f"<td>{rule['count']}</td><td>{rule['help']}</td></tr>"
                )
            rows.append("</table>")
            rows.append("<p><a href='a11y-report.json'>a11y-report.json</a></p>")

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
                "a11y": {
                    "total": a11y["total_violations"],
                    "by_impact": a11y["by_impact"],
                },
            }, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )

        self.write_markdown_index(exit_code, a11y)

    def write_markdown_index(self, exit_code: int, a11y: dict):
        fails = [v for v in self.violations if v["severity"] == "FAIL"]
        warns = [v for v in self.violations if v["severity"] == "WARN"]
        lines = [
            "# Indeks Artefak Browser Smoke SIILS",
            "",
            f"- Dibuat: {datetime.now().isoformat()}",
            f"- Base URL: {self.base_url}",
            f"- Exit code: **{exit_code}**",
            f"- Pelanggaran: {len(fails)} FAIL, {len(warns)} WARN",
            f"- Aksesibilitas (axe-core): {a11y['total_violations']} temuan — {a11y['by_impact']}",
            "",
            "## Berkas",
            "",
            "- `index.html` — laporan visual (violations, a11y, galeri screenshot)",
            "- `report.json` — ringkasan mesin (violations, screenshots, a11y)",
            "- `a11y-report.json` — rincian temuan axe per rule/halaman/viewport",
            "- `screenshots/` — tangkapan layar tiap role, halaman, dan viewport",
            "- `downloads/` — berkas hasil unduhan (template impor, dataset uji)",
            "",
            "## Halaman laporan yang ditelusuri",
            "",
        ]
        for report_type in REPORT_TYPES:
            lines.append(f"- `/reports?type={report_type}` (+ ekspor PDF/Excel/Cetak)")
        lines += [
            "",
            "## Skenario alur",
            "",
            "- Pengajuan → verifikasi Sarpras → persetujuan Kepsek → realisasi.",
            "- Perubahan status unit individual (modal, alasan wajib, riwayat bertambah).",
            "- Peminjaman terlambat pada halaman detail.",
            "- Impor inventaris: unduh template, pratinjau salah/benar, konfirmasi.",
            "",
        ]
        if fails:
            lines.append("## Kegagalan")
            lines.append("")
            for v in fails:
                lines.append(f"- [{v['role']}/{v['page']} @ {v['viewport']}] {v['message']}")
            lines.append("")
        (self.artifact_dir / "INDEX.md").write_text("\n".join(lines), encoding="utf-8")


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
            smoke.run_reports(browser)
            smoke.run_status_unit(browser)
            smoke.run_overdue_loan(browser)
            smoke.run_import_flow(browser)
            smoke.run_special_pages(browser)
        except Exception as exc:
            smoke.fail(f"Eksekusi gagal: {exc}")
            exit_code = 1
        finally:
            browser.close()

    smoke.check_a11y()

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