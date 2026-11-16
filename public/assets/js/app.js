/**
 * GPON MANAGER — CORE CLIENT JAVASCRIPT
 * Di-port dari fiberhome-master (app.js), tanpa dark mode & OLT switcher.
 * Tambahan untuk CodeIgniter 4: helper CSRF (hash dibaca dari cookie supaya
 * tetap valid setelah regenerate) dan App.fetch() pengganti boilerplate
 * `fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>')` di tiap view.
 */

const App = {
  // Di-override dari layout: { name: 'csrf_test_name', hash: '...' }
  csrf: { name: 'csrf_test_name', hash: '' },

  init() {
    this.initSerialAutoFormatters();
    this.initModalDismiss();
  },

  // ------------------ CSRF ------------------
  getCookie(name) {
    const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : null;
  },

  /** Hash CSRF segar: CI4 cookie-based + regenerate=true mengrotasi hash tiap POST,
   *  jadi selalu utamakan cookie di atas hash yang di-render saat halaman dimuat. */
  csrfHash() {
    return this.getCookie('csrf_cookie_name') || this.csrf.hash;
  },

  /** fetch() + CSRF otomatis. Body FormData ditambah field token, request lain
   *  dikirim via header. Untuk body JSON, gunakan opts.json (object) agar token
   *  ikut disisipkan. */
  fetch(url, opts = {}) {
    const hash = this.csrfHash();
    opts.headers = Object.assign({}, opts.headers || {});
    opts.headers['X-CSRF-TOKEN'] = hash;
    if (opts.body instanceof FormData) {
      opts.body.append(this.csrf.name, hash);
    } else if (opts.json !== undefined) {
      opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
      opts.body = JSON.stringify(Object.assign({}, opts.json, { [this.csrf.name]: hash }));
      delete opts.json;
    }
    return fetch(url, opts);
  },

  // ------------------ Toast Notification Hub ------------------
  showToast(message, type = "info", duration = 4000) {
    let container = document.getElementById("toastContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "toastContainer";
      container.className = "toast-container";
      document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = `toast ${type}`;

    let icon = "ℹ️";
    if (type === "success") icon = "✅";
    if (type === "error") icon = "⚠️";
    if (type === "warning") icon = "⚡";

    toast.innerHTML = `<span>${icon}</span> <div>${message}</div>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(100%)";
      toast.style.transition = "all 0.3s ease";
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  /** Baca JSON dengan aman. Melempar Error berisi sebab yang bisa ditindaklanjuti.
   *  Respon sukses-tapi-success:false TIDAK dilempar — biarkan view yang menangani. */
  async readJson(res, fallback = "Permintaan gagal") {
    const teks = await res.text();
    let data = null;
    try { data = JSON.parse(teks); } catch (e) { /* bukan JSON */ }

    if (data === null) {
      // HTML = halaman error dari reverse-proxy / gateway, bukan dari aplikasi
      if (res.status === 504 || /gateway time-?out/i.test(teks)) {
        throw new Error("Permintaan ke OLT melebihi batas waktu gateway (504). "
          + "OLT biasanya sedang sibuk dipakai proses lain — tunggu sebentar lalu ulangi.");
      }
      if (res.status === 502 || res.status === 503) {
        throw new Error(`Server tidak merespons dengan benar (HTTP ${res.status}). `
          + "Cek apakah aplikasi sedang restart.");
      }
      throw new Error(`Respons bukan JSON (HTTP ${res.status}). `
        + "Biasanya ini halaman error dari proxy, bukan dari aplikasi.");
    }

    if (!res.ok) {
      const d = data.detail !== undefined ? data.detail
        : data.message !== undefined ? data.message
        : data.error !== undefined ? data.error : null;
      const pesan = typeof d === "string" ? d
        : Array.isArray(d) ? d.map(e => (e.loc ? e.loc[e.loc.length - 1] + ": " : "") + (e.msg || "")).join("; ")
        : "";
      if (res.status === 401) throw new Error("Sesi login habis — muat ulang halaman lalu login lagi.");
      if (res.status === 403) throw new Error("Aksi ditolak (403). Token keamanan kedaluwarsa — muat ulang halaman lalu coba lagi.");
      throw new Error(pesan || `${fallback} (HTTP ${res.status})`);
    }
    return data;
  },

  // ------------------ Modal Management ------------------
  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add("active");
  },

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove("active");
  },

  /** Klik backdrop / Esc menutup modal mana pun yang aktif.
   *  Modal dengan class .modal-static tidak bisa ditutup lewat backdrop/Esc. */
  initModalDismiss() {
    document.addEventListener("click", (e) => {
      if (e.target.classList && e.target.classList.contains("modal-overlay")
          && !e.target.classList.contains("modal-static")) {
        e.target.classList.remove("active");
      }
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        document.querySelectorAll(".modal-overlay.active:not(.modal-static)").forEach(m => m.classList.remove("active"));
      }
    });
  },

  // ------------------ Clipboard ------------------
  copyText(text, label = "Teks") {
    navigator.clipboard.writeText(text).then(() => {
      this.showToast(`${label} berhasil disalin ke clipboard!`, "success", 2000);
    });
  },

  // ------------------ Loading state tombol ------------------
  /** Disable tombol + ganti isinya selama proses berjalan.
   *  App.setLoading(btn, true, 'Menyimpan...') / App.setLoading(btn, false) */
  setLoading(btn, isLoading, loadingText = "Memproses...") {
    if (!btn) return;
    if (isLoading) {
      if (btn.dataset.originalHtml === undefined) {
        btn.dataset.originalHtml = btn.innerHTML;
      }
      btn.disabled = true;
      btn.innerHTML = `<span class="spin ${btn.classList.contains('btn-primary') ? '' : 'spin-dark'}"></span> ${loadingText}`;
    } else {
      btn.disabled = false;
      if (btn.dataset.originalHtml !== undefined) {
        btn.innerHTML = btn.dataset.originalHtml;
      }
      this.refreshIcons();
    }
  },

  // ------------------ SN Auto Normalization ------------------
  // Rule: FHTT/FHSC uppercase + 8 hex digits lowercase
  normalizeSerial(sn) {
    sn = sn.trim().replace(/[:\s]/g, "");
    if (sn.length < 4) return sn.toUpperCase();
    const prefix = sn.slice(0, 4).toUpperCase();
    const hexpart = sn.slice(4).toLowerCase();
    return prefix + hexpart;
  },

  initSerialAutoFormatters() {
    document.querySelectorAll(".input-serial").forEach((input) => {
      input.addEventListener("input", (e) => {
        const start = e.target.selectionStart;
        e.target.value = this.normalizeSerial(e.target.value);
        e.target.setSelectionRange(start, start);
      });
    });
  },

  // ------------------ Kotak pesan inline ------------------
  /** Isi elemen dengan kotak pesan berwarna.
   *  App.setMsg(el, 'success', 'Tersimpan.') — type: loading|success|error|warning|info */
  setMsg(el, type, html) {
    if (!el) return;
    const styles = {
      loading: 'mt-2 text-xs text-slate-400 flex items-center gap-1.5',
      success: 'mt-2 p-2.5 rounded-lg text-xs bg-emerald-50 border border-emerald-200 text-emerald-700',
      error:   'mt-2 p-2.5 rounded-lg text-xs bg-rose-50 border border-rose-200 text-rose-700',
      warning: 'mt-2 p-2.5 rounded-lg text-xs bg-amber-50 border border-amber-200 text-amber-800',
      info:    'mt-2 p-2.5 rounded-lg text-xs bg-blue-50 border border-blue-100 text-blue-700',
    };
    el.className = styles[type] || styles.info;
    el.innerHTML = type === 'loading'
      ? '<span class="spin spin-dark"></span>' + html
      : html;
    el.classList.remove('hidden');
  },

  // ------------------ Util ------------------
  refreshIcons() {
    if (window.lucide) window.lucide.createIcons();
  },

  escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  },

  debounce(fn, ms = 350) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  },

  // ------------------ Formatters ------------------
  formatDbm(val) {
    if (val === null || val === undefined) return "N/A";
    return `${parseFloat(val).toFixed(2)} dBm`;
  },

  getSignalBadgeHtml(rxPower, isOnline = true) {
    if (!isOnline || rxPower === null || rxPower === undefined || rxPower < -32) {
      return '<span class="badge-signal offline"><span class="pulsing-dot"></span> Offline</span>';
    }
    if (rxPower > -24.0) {
      return `<span class="badge-signal good">🟢 ${rxPower.toFixed(2)} dBm (Bagus)</span>`;
    }
    if (rxPower >= -27.0) {
      return `<span class="badge-signal warning">🟡 ${rxPower.toFixed(2)} dBm (Waspada)</span>`;
    }
    return `<span class="badge-signal critical">🔴 ${rxPower.toFixed(2)} dBm (Kritis)</span>`;
  }
};

// Global initializer
document.addEventListener("DOMContentLoaded", () => {
  App.init();
});
