(function () {
  "use strict";

  // ---------- helpers ----------
  const norm = (s) =>
    String(s || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/đ/g, "d")
      .replace(/[^a-z0-9]+/g, " ")
      .trim();
  const bare = (s) =>
    norm(s).replace(/^(thanh pho|tinh|phuong|xa|thi tran|dac khu)\s+/, "");
  const locationKey = (s) =>
    bare(s)
      .replace(/\b(city|province|district|ward|commune)\b/g, "")
      .replace(/\s+/g, " ")
      .trim();
  const has = (hay, needle) => !!needle && ` ${hay} `.includes(` ${needle} `); // khớp nguyên cụm: "phuong 1" ≠ "phuong 12"
  const byLen = (a) => [...a].sort((x, y) => y.name.length - x.name.length);
  const escHtml = (s) =>
    String(s).replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  const escRe = (s) => String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const t = (k, fb) => window.__HUIYI_I18N__?.sepay?.[k] || fb;

  /** Xóa các tên (tỉnh/phường/...) khỏi chuỗi địa chỉ, phần còn lại là số nhà/đường. */
  function stripNames(text, names) {
    let s = String(text || "");
    [...names, "Việt Nam"]
      .filter(Boolean)
      .sort((a, b) => b.length - a.length)
      .forEach((n) => {
        s = s.replace(
          new RegExp(`(?<![\\p{L}\\p{N}])${escRe(n)}(?![\\p{L}\\p{N}])`, "giu"),
          "",
        );
      });
    return s
      .split(",")
      .map((x) => x.trim())
      .filter(Boolean)
      .join(", ");
  }

  document.addEventListener("DOMContentLoaded", () => {
    const wrap = document.querySelector("[data-address-cascade]");
    if (!wrap) return;

    const jsonUrl =
      wrap.dataset.jsonUrl || "/assets/data/vietnam-provinces.json";
    const locEndpoint = wrap.dataset.locationEndpoint || "/api/location.php";
    const $ = (s) => wrap.querySelector(s);

    const provInput = document.getElementById("address-province-input");
    const provDrop = $("[data-province-dropdown]");
    const provCode = $("[data-province-code]");
    const provName = $("[data-province-name]");
    const wardInput = document.getElementById("address-ward-input");
    const wardDrop = $("[data-ward-dropdown]");
    const wardCode = $("[data-ward-code]");
    const wardName = $("[data-ward-name]");
    const streetInput = $("[data-street-address]");
    const fullField = document.getElementById("member-profile-address");
    const btnGps = $("[data-btn-gps]");
    const warnBox = $("[data-address-warning]");
    const form = wrap.closest("form");

    let provinces = null;
    let curProv = null;

    // ---------- UI ----------
    const warn = (msg) => {
      if (!warnBox) return;
      warnBox.textContent = msg ? `⚠️ ${msg}` : "";
      warnBox.classList.toggle("is-visible", !!msg);
    };

    const sync = () => {
      const full = [streetInput?.value, wardName?.value, provName?.value]
        .map((v) => (v || "").trim())
        .filter(Boolean)
        .join(", ");
      if (fullField) fullField.value = full;
      return full;
    };

    const closeDrop = (d) =>
      d && (d.classList.remove("is-open"), (d.innerHTML = ""));

    const renderDrop = (drop, items, icon, emptyText, onPick) => {
      if (!drop) return;
      if (!items.length) {
        drop.innerHTML = `<div class="autocomplete-item autocomplete-no-result">${escHtml(emptyText)}</div>`;
      } else {
        const shown = items.slice(0, 60);
        drop.innerHTML = shown
          .map(
            (it) =>
              `<div class="autocomplete-item" data-code="${escHtml(it.code)}"><span class="autocomplete-item-icon">${icon}</span><span>${escHtml(it.name)}${it.extra || ""}</span></div>`,
          )
          .join("");
        drop
          .querySelectorAll(".autocomplete-item[data-code]")
          .forEach((el, i) => (el.onclick = () => onPick(shown[i])));
      }
      drop.classList.add("is-open");
    };

    const mark = (input, item) => {
      input.classList.toggle("is-location-verified", !!item);
      input.classList.remove("has-location-error");
    };

    // ---------- chọn Tỉnh / Phường ----------
    const resetWard = (disabled) => {
      if (!wardInput) return;
      wardInput.value = "";
      wardInput.disabled = disabled;
      wardInput.placeholder = disabled
        ? t("addr_ward_placeholder_disabled", "Vui lòng chọn Tỉnh trước...")
        : t("addr_ward_placeholder", "Nhập tìm Phường / Xã...");
      mark(wardInput, null);
      if (wardCode) wardCode.value = "";
      if (wardName) wardName.value = "";
    };

    const pickProv = (p, doSync = true) => {
      curProv = p;
      provInput.value = p.name;
      mark(provInput, p);
      if (provCode) provCode.value = p.code;
      if (provName) provName.value = p.name;
      resetWard(false);
      closeDrop(provDrop);
      if (doSync) sync();
    };

    const pickWard = (w, doSync = true) => {
      wardInput.value = w.name;
      mark(wardInput, w);
      if (wardCode) wardCode.value = w.code;
      if (wardName) wardName.value = w.name;
      closeDrop(wardDrop);
      if (doSync) sync();
    };

    const showProv = () => {
      if (!provinces) return;
      const q = norm(provInput.value);
      renderDrop(
        provDrop,
        q ? provinces.filter((p) => p._k.includes(q)) : provinces,
        "📍",
        t("addr_no_province_match", "Không tìm thấy Tỉnh / Thành nào"),
        (p) => {
          pickProv(p);
          setTimeout(() => wardInput?.focus(), 80);
        },
      );
    };

    const showWard = () => {
      if (!curProv || wardInput.disabled) return;
      const q = norm(wardInput.value);
      const items = (
        q
          ? curProv.wards.filter((w) => w._ks.some((k) => k.includes(q)))
          : curProv.wards
      ).map((w) => {
        const old =
          q && !norm(w.name).includes(q)
            ? w._ns.slice(1).find((o) => norm(o).includes(q))
            : "";
        return {
          ...w,
          extra: old
            ? ` <small style="color:#64748b;font-size:0.8rem;">(${escHtml(old)})</small>`
            : "",
        };
      });
      renderDrop(
        wardDrop,
        items,
        "🏘️",
        t("addr_no_ward_match", "Không tìm thấy Phường / Xã nào"),
        (w) => {
          pickWard(w);
          setTimeout(() => streetInput?.focus(), 80);
        },
      );
    };

    provInput.addEventListener("focus", showProv);
    provInput.addEventListener("input", () => {
      // Gõ lại tên tỉnh = bỏ lựa chọn cũ (tránh lưu nhầm tỉnh cũ)
      curProv = null;
      mark(provInput, null);
      if (provCode) provCode.value = "";
      if (provName) provName.value = "";
      resetWard(true);
      sync();
      showProv();
    });

    wardInput?.addEventListener("focus", showWard);
    wardInput?.addEventListener("input", () => {
      mark(wardInput, null);
      if (wardCode) wardCode.value = "";
      if (wardName) wardName.value = "";
      sync();
      showWard();
    });

    streetInput?.addEventListener("input", sync);

    // Enter = chọn kết quả đầu, Escape = đóng
    [
      [provInput, provDrop],
      [wardInput, wardDrop],
    ].forEach(([input, drop]) =>
      input?.addEventListener("keydown", (e) => {
        if (!drop?.classList.contains("is-open")) return;
        if (e.key === "Escape") closeDrop(drop);
        else if (e.key === "Enter") {
          e.preventDefault();
          drop.querySelector(".autocomplete-item[data-code]")?.click();
        }
      }),
    );

    document.addEventListener("click", (e) => {
      if (
        !e.target.closest("#address-province-input, [data-province-dropdown]")
      )
        closeDrop(provDrop);
      if (!e.target.closest("#address-ward-input, [data-ward-dropdown]"))
        closeDrop(wardDrop);
    });

    // ---------- khớp text địa chỉ với Tỉnh / Phường ----------
    const matchProv = (name, addr) => {
      const na = norm(addr);
      const names = Array.isArray(name) ? name : [name];
      return (
        provinces.find((p) =>
          names.some(
            (candidate) =>
              candidate && locationKey(p.name) === locationKey(candidate),
          ),
        ) ||
        byLen(provinces).find(
          (p) => has(na, p._k) || has(na, locationKey(p.name)),
        ) ||
        null
      );
    };

    const matchWard = (p, name, addr) => {
      const na = norm(addr);
      const names = Array.isArray(name) ? name : [name];
      return (
        p.wards.find(
          (w) =>
            names.some(
              (candidate) =>
                candidate &&                 w._ns.some((n) => locationKey(n) === locationKey(candidate)),
            ),
        ) ||
        byLen(p.wards).find((w) =>
          w._ks.some((k) => has(na, k) || has(na, locationKey(k))),
        ) ||
        null
      );
    };

    /** Dùng chung cho: địa chỉ đã lưu và kết quả GPS. */
    function fillFrom({ province, ward, street, addr }) {
      const p = matchProv(province, addr);
      let w = null;
      if (p) {
        pickProv(p, false);
        w = matchWard(p, ward, addr);
        if (w) pickWard(w, false);
      }
      if (streetInput) {
        const st =
          street ?? stripNames(addr, p ? [...p._ns, ...(w ? w._ns : [])] : []);
        if (st) {
          streetInput.value = st;
          streetInput.classList.remove("has-location-error");
        }
      }
      sync();
      return { p, w };
    }

    // ---------- tải dữ liệu ----------
    fetch(jsonUrl)
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((data) => {
        if (!Array.isArray(data)) return;
        data.forEach((p) => {
          p._k = norm(p.name);
          p._ns = [p.name];
          p.wards = p.wards || [];
          p.wards.forEach((w) => {
            w._ns = [w.name, ...(w.old_names || [])];
            w._ks = w._ns.map(norm);
          });
        });
        provinces = data;

        const saved = fullField?.value.trim();
        if (!saved) return;
        const { p } = fillFrom({ addr: saved });
        if (!p && streetInput) streetInput.value = saved; // không khớp tỉnh: giữ nguyên để khách không mất dữ liệu
        sync();
      })
      .catch((e) => {
        console.error("[Address] load error:", e);
        warn(
          t(
            "addr_err_load_provinces",
            "Không thể tải danh sách tỉnh thành. Vui lòng tải lại trang.",
          ),
        );
      });

    // ---------- GPS thật ----------
    const reverseGeo = async (lat, lng) => {
      try {
        // Google Maps Geocoding API qua backend
        const r = await fetch(
          `${locEndpoint}?action=reverse_geocode&lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`,
        );
        if (r.ok) {
          const first = (await r.json()).results?.[0];
          if (first) {
            const components = first.address_components || [];
            const component = (types) =>
              components
                .filter((item) => types.some((type) => item.types?.includes(type)))
                .map((item) => item.long_name)
                .filter(Boolean);
            const a = first.formatted_address || "";
            const province = component([
              "administrative_area_level_1",
              "administrative_area_level_2",
              "locality",
            ]);
            const ward = component([
              "administrative_area_level_3",
              "sublocality_level_1",
              "sublocality_level_2",
              "sublocality",
              "neighborhood",
              "administrative_area_level_2",
            ]);
            const streetParts = component(["street_number", "route"]);
            return {
              addr: a,
              province,
              ward,
              street: streetParts.join(" ") || a.split(",")[0].trim(),
            };
          }
        }
      } catch (e) {
        console.warn("[Address] Google reverse geocoding failed:", e);
      }
      throw new Error("Không thể tra cứu địa chỉ bằng Google Maps.");
    };

    btnGps?.addEventListener("click", (e) => {
      e.preventDefault();
      if (btnGps.classList.contains("is-detecting")) return;
      warn(null);
      if (!navigator.geolocation)
        return warn("Trình duyệt không hỗ trợ định vị GPS.");
      if (!provinces)
        return warn("Đang tải danh sách tỉnh thành, vui lòng thử lại sau.");

      const label = btnGps.querySelector(".btn-gps-text");
      const done = () => {
        btnGps.classList.remove("is-detecting");
        if (label)
          label.textContent = t("gps_use_current", "Dùng vị trí hiện tại");
      };
      btnGps.classList.add("is-detecting");
      if (label) label.textContent = t("gps_locating", "Đang định vị...");

      navigator.geolocation.getCurrentPosition(
        async ({ coords }) => {
          try {
            const { p, w } = fillFrom(
              await reverseGeo(coords.latitude, coords.longitude),
            );
            if (!p || !w)
              warn(
                "Đã lấy vị trí nhưng chưa khớp đủ Tỉnh/Phường. Vui lòng kiểm tra lại bên dưới.",
              );
          } catch (err) {
            console.warn("[Address GPS]", err);
            warn(
              "Không thể nhận diện địa chỉ từ GPS. Vui lòng chọn Tỉnh/Phường bên dưới.",
            );
          } finally {
            done();
          }
        },
        (err) => {
          done();
          warn(
            {
              1: "Bạn đã từ chối quyền vị trí. Vui lòng bật lại hoặc chọn thủ công.",
              2: "Không thể xác định vị trí.",
              3: "Hết thời gian chờ GPS.",
            }[err.code] || "Không thể lấy vị trí GPS.",
          );
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
      );
    });

    // ---------- submit: chặn nếu gõ Tỉnh/Phường mà chưa chọn từ danh sách ----------
    form?.addEventListener("submit", (e) => {
      const bad = [
        [provInput, provName],
        [wardInput, wardName],
      ].filter(([i, h]) => i && h && i.value.trim() && !h.value);
      if (bad.length) {
        e.preventDefault();
        bad.forEach(([i]) => i.classList.add("has-location-error"));
        warn(
          t(
            "addr_pick_from_list",
            "Vui lòng chọn Tỉnh/Phường từ danh sách gợi ý.",
          ),
        );
        bad[0][0].focus();
        return;
      }
      sync();
    });
  });
})();
