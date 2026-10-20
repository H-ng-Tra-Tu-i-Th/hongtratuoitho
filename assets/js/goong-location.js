/**
 * HUIYI Tea - Goong Maps Location & Nearest Store Selector
 * Implements:
 * 1. GoongMapService (AutoComplete, Place Detail, Forward Geocode, Distance Matrix)
 * 2. LocationOrderManager (GPS/Manual Coordinates resolution, Nearest Store calculation, Manual selection)
 * 3. UI Controller (Banner, Modal, Debounced Autocomplete, GPS trigger)
 */

(function () {
  "use strict";

  function t(key, fallback, vars = {}) {
    const dict =
      window.__HUIYI_I18N__ && window.__HUIYI_I18N__.sepay
        ? window.__HUIYI_I18N__.sepay
        : {};
    let text = dict[key] || fallback;
    for (const [k, v] of Object.entries(vars)) {
      text = text.replace(new RegExp(`\\{${k}\\}`, "g"), v);
    }
    return text;
  }

  class GoongMapService {
    constructor(config = {}) {
      this.endpoint = (config.endpoint || "/api/location.php").replace(
        /\/+$/,
        "",
      );
      this.timeoutMs = config.timeoutMs || 8000;
    }

    async fetchWithTimeout(url, signal) {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), this.timeoutMs);
      if (signal) {
        signal.addEventListener("abort", () => controller.abort(), {
          once: true,
        });
      }

      try {
        const res = await fetch(url, { signal: controller.signal });
        if (!res.ok) {
          throw new Error(`Location API HTTP ${res.status}: ${res.statusText}`);
        }
        return await res.json();
      } catch (err) {
        if (err.name === "AbortError") {
          throw new Error(
            `Yêu cầu định vị quá thời gian (${this.timeoutMs}ms).`,
          );
        }
        throw err;
      } finally {
        clearTimeout(timer);
      }
    }

    // 1. Forward Geocoding
    async forwardGeocode(address, signal) {
      const q = (address || "").trim();
      if (!q) throw new Error("Địa chỉ không được để trống.");
      const url = `${this.endpoint}?action=geocode&address=${encodeURIComponent(q)}`;
      const data = await this.fetchWithTimeout(url, signal);
      const first = data.results?.[0];
      if (!first || !first.geometry?.location) {
        throw new Error(
          "Không tìm thấy tọa độ cho địa chỉ này. Vui lòng kiểm tra lại.",
        );
      }
      return {
        coordinates: first.geometry.location,
        formattedAddress: first.formatted_address || q,
      };
    }

    // 2. Reverse Geocoding: chuyển tọa độ lat, lng thành địa chỉ chi tiết
    async reverseGeocode(lat, lng, signal) {
      const url = `${this.endpoint}?action=reverse_geocode&lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`;
      const data = await this.fetchWithTimeout(url, signal);
      const first = data.results?.[0];
      if (!first || !first.formatted_address) {
        throw new Error("Không tìm thấy địa chỉ cho tọa độ này.");
      }
      return {
        coordinates: first.geometry?.location || { lat, lng },
        formattedAddress: first.formatted_address,
        compound: first.compound || null,
        addressComponents: first.address_components || [],
      };
    }

    // 3. Distance Matrix
    async distanceMatrix(origin, destinations, vehicle = "bike", signal) {
      if (!destinations || destinations.length === 0) return [];
      const originStr = `${origin.lat},${origin.lng}`;
      const destsStr = destinations.map((d) => `${d.lat},${d.lng}`).join("|");
      const url = `${this.endpoint}?action=distance_matrix&origins=${encodeURIComponent(originStr)}&destinations=${encodeURIComponent(destsStr)}&vehicle=${encodeURIComponent(vehicle)}`;
      const data = await this.fetchWithTimeout(url, signal);
      return data.rows?.[0]?.elements || [];
    }
  }

  class LocationOrderManager {
    constructor(goongService, initialStores = []) {
      this.goongService = goongService;
      this.state = {
        userLocation: null,
        userAddress: "",
        storesWithDistance: initialStores.map((s) => ({ ...s })),
        selectedStore: null,
        nearestStore: null,
        isLoading: false,
        error: null,
      };
      this.listeners = new Set();
    }

    getState() {
      return this.state;
    }

    subscribe(fn) {
      this.listeners.add(fn);
      fn(this.state);
      return () => this.listeners.delete(fn);
    }

    updateState(patch) {
      this.state = Object.assign({}, this.state, patch);
      this.listeners.forEach((fn) => {
        try {
          fn(this.state);
        } catch (e) {
          console.error(e);
        }
      });
    }

    setStores(stores) {
      this.updateState({ storesWithDistance: stores.map((s) => ({ ...s })) });
    }

    // Giai đoạn 1: Thu thập vị trí người dùng
    async resolveUserCoordinates(params) {
      this.updateState({ isLoading: true, error: null });
      try {
        let coords;
        let address = "";

        if (params.method === "gps") {
          coords = await this.getGPSLocation();
          address = `Tọa độ GPS (${coords.lat.toFixed(5)}, ${coords.lng.toFixed(5)})`;
          if (this.goongService && this.goongService.apiKey) {
            try {
              const rev = await this.goongService.reverseGeocode(
                coords.lat,
                coords.lng,
              );
              if (rev && rev.formattedAddress) {
                address = rev.formattedAddress;
              }
            } catch (revErr) {
              console.warn(
                "[Location] Reverse geocoding fallback:",
                revErr.message,
              );
            }
          }
        } else if (params.method === "place_id") {
          const detail = await this.goongService.placeDetail(params.placeId);
          coords = detail.coordinates;
          address = params.description || detail.formattedAddress;
        } else if (params.method === "manual_address") {
          const geo = await this.goongService.forwardGeocode(params.address);
          coords = geo.coordinates;
          address = geo.formattedAddress || params.address;
        }

        this.updateState({
          userLocation: coords,
          userAddress: address,
          isLoading: false,
        });
        return coords;
      } catch (err) {
        this.updateState({ isLoading: false, error: err.message });
        throw err;
      }
    }

    // Giai đoạn 2: Tính khoảng cách & Chọn chi nhánh tối ưu
    async calculateNearestStore(userCoords, storesList) {
      let stores = storesList || this.state.storesWithDistance;
      if ((!stores || stores.length === 0) && this.storesPromise) {
        const fetched = await this.storesPromise;
        stores = (fetched && fetched.length > 0) ? fetched : this.state.storesWithDistance;
      }
      if (!stores || stores.length === 0)
        return { nearest: null, sortedStores: [] };

      this.updateState({ isLoading: true, error: null });
      try {
        const validStores = [];
        const invalidStores = [];

        stores.forEach((s) => {
          if (
            typeof s.lat === "number" &&
            typeof s.lng === "number" &&
            !isNaN(s.lat) &&
            !isNaN(s.lng)
          ) {
            validStores.push(s);
          } else {
            invalidStores.push(
              Object.assign({}, s, { matrixStatus: "NO_COORDINATES" }),
            );
          }
        });

        let evaluatedStores = [];
        if (validStores.length > 0) {
          const destinations = validStores.map((s) => ({
            lat: s.lat,
            lng: s.lng,
          }));
          const elements = await this.goongService.distanceMatrix(
            userCoords,
            destinations,
            "bike",
          );

          validStores.forEach((store, i) => {
            const el = elements[i];
            if (el && el.status === "OK" && el.distance) {
              evaluatedStores.push(
                Object.assign({}, store, {
                  distance: el.distance,
                  duration: el.duration,
                  matrixStatus: "OK",
                }),
              );
            } else {
              evaluatedStores.push(
                Object.assign({}, store, {
                  matrixStatus: el?.status || "ERROR",
                }),
              );
            }
          });
        }

        const okStores = evaluatedStores.filter(
          (s) => s.matrixStatus === "OK" && s.distance,
        );
        okStores.sort(
          (a, b) => (a.distance.value || 0) - (b.distance.value || 0),
        );

        const remaining = [
          ...evaluatedStores.filter((s) => s.matrixStatus !== "OK"),
          ...invalidStores,
        ];
        const sortedStores = [...okStores, ...remaining];
        const nearest = okStores[0] || sortedStores[0] || null;

        this.updateState({
          storesWithDistance: sortedStores,
          nearestStore: nearest,
          selectedStore: nearest,
          isLoading: false,
        });

        return { nearest, sortedStores };
      } catch (err) {
        this.updateState({ isLoading: false, error: err.message });
        throw err;
      }
    }

    // Giai đoạn 3: UX/UI & Quyền thay đổi chi nhánh
    selectStore(storeId) {
      const found = this.state.storesWithDistance.find(
        (s) => Number(s.id) === Number(storeId),
      );
      if (found) {
        this.updateState({ selectedStore: found });
      }
      return found;
    }

    getGPSLocation() {
      return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
          return reject(new Error("Trình duyệt không hỗ trợ định vị GPS."));
        }
        navigator.geolocation.getCurrentPosition(
          (pos) =>
            resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
          (err) => {
            let msg = "Không thể lấy vị trí hiện tại.";
            if (err.code === 1)
              msg =
                "Bạn đã từ chối quyền truy cập vị trí. Vui lòng nhập địa chỉ bằng tay.";
            else if (err.code === 2) msg = "Vị trí hiện tại không khả dụng.";
            else if (err.code === 3) msg = "Định vị GPS hết thời gian chờ.";
            reject(new Error(msg));
          },
          { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 },
        );
      });
    }

    static debounce(fn, waitMs = 400) {
      let timer = null;
      return function (...args) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), waitMs);
      };
    }
  }

  // --- UI BINDING & CONTROLLER ---
  function initLocationSelector() {
    const wrap = document.querySelector("[data-location-container]");
    if (!wrap) return;

    const locationEndpoint =
      wrap.getAttribute("data-location-endpoint") || "/api/location.php";
    const goong = new GoongMapService({ endpoint: locationEndpoint });
    const manager = new LocationOrderManager(goong);

    const bannerEl = wrap.querySelector("[data-location-banner]");
    const bannerStoreTitle = wrap.querySelector("[data-banner-store-name]");
    const bannerDistanceWrap = wrap.querySelector(
      "[data-banner-distance-wrap]",
    );
    const bannerDistanceText = wrap.querySelector(
      "[data-banner-distance-text]",
    );
    const bannerDurationText = wrap.querySelector(
      "[data-banner-duration-text]",
    );
    const bannerShippingText = wrap.querySelector(
      "[data-banner-shipping-text]",
    );
    const reviewShippingWrap = document.querySelector(
      "[data-review-shipping-wrap]",
    );
    const reviewShippingText = document.querySelector(
      "[data-review-shipping-text]",
    );
    const bannerReasonWrap = wrap.querySelector("[data-banner-reason-wrap]");
    const bannerReasonText = wrap.querySelector("[data-banner-reason-text]");
    const btnChangeStore = wrap.querySelector("[data-btn-change-store]");
    const hiddenStoreInput = document.querySelector(
      '[name="selected_store_id"]',
    );

    const addressInput = document.querySelector('[name="address"]');
    const btnGps = wrap.querySelector("[data-btn-gps]");
    const autocompleteDropdown = wrap.querySelector(
      "[data-autocomplete-dropdown]",
    );
    const addressWarning = wrap.querySelector("[data-address-warning]");
    const verifiedInput = wrap.querySelector("[data-address-verified-input]");
    const checkoutForm = document.querySelector("[data-checkout-form]");

    let isAddressVerified = false;
    let verifiedAddressText = "";

    function setAddressVerified(text) {
      isAddressVerified = true;
      verifiedAddressText = text;
      if (verifiedInput) verifiedInput.value = "1";
      if (addressWarning) {
        addressWarning.classList.remove("is-visible");
        addressWarning.textContent = "";
      }
      if (addressInput) {
        addressInput.classList.remove("has-location-error");
        addressInput.classList.add("is-location-verified");
      }
    }

    function setAddressUnverified(customMessage) {
      isAddressVerified = false;
      if (verifiedInput) verifiedInput.value = "0";
      if (addressInput) {
        addressInput.classList.remove("is-location-verified");
      }
      if (customMessage && addressWarning) {
        addressWarning.textContent = "⚠️ " + customMessage;
        addressWarning.classList.add("is-visible");
      }
    }

    const modalBackdrop = document.querySelector("[data-store-modal]");
    const modalClose = modalBackdrop
      ? modalBackdrop.querySelector("[data-modal-close]")
      : null;
    const storeListContainer = modalBackdrop
      ? modalBackdrop.querySelector("[data-store-list]")
      : null;
    const storeSearchInput = modalBackdrop
      ? modalBackdrop.querySelector("[data-store-search]")
      : null;

    // Load stores from API
    const storesPromise = fetch(
      wrap.getAttribute("data-stores-endpoint") || "/api/stores.php",
    )
      .then((r) => r.json())
      .then((res) => {
        if (res.success && Array.isArray(res.data)) {
          manager.setStores(res.data);
          return res.data;
        }
        return [];
      })
      .catch((e) => {
        console.warn("[Location] Failed to fetch stores:", e);
        return [];
      });
    manager.storesPromise = storesPromise;

    // Subscribe to state updates to render banner & modal
    manager.subscribe((state) => {
      if (!bannerEl) return;
      const bannerIcon = bannerEl.querySelector(".location-banner-icon");

      if (state.isLoading) {
        bannerEl.classList.add("is-loading");
        bannerEl.classList.remove("is-updated");
        if (bannerIcon) bannerIcon.classList.add("is-spinning");
        if (bannerReasonWrap) bannerReasonWrap.style.display = "none";
        if (bannerShippingText) bannerShippingText.style.display = "none";
        if (reviewShippingWrap) reviewShippingWrap.style.display = "none";

        bannerStoreTitle.textContent = t(
          "gps_calculating_nearest",
          "Đang tính toán khoảng cách và tìm chi nhánh gần nhất...",
        );
        bannerDistanceWrap.style.display = "inline-flex";
        bannerDistanceText.textContent = t(
          "gps_calculating_distance",
          "Đang tính km...",
        );
        bannerDurationText.textContent = t(
          "gps_scanning_route",
          "⏱ Đang quét đường xe chạy",
        );
        if (btnChangeStore) {
          btnChangeStore.disabled = true;
          btnChangeStore.style.opacity = "0.6";
          btnChangeStore.textContent = t("gps_calculating", "Đang tính...");
        }
      } else if (state.selectedStore) {
        bannerEl.classList.remove("is-loading");
        if (bannerIcon) bannerIcon.classList.remove("is-spinning");
        if (btnChangeStore) {
          btnChangeStore.disabled = false;
          btnChangeStore.style.opacity = "1";
          btnChangeStore.textContent = t("gps_change_store", "Đổi chi nhánh");
        }

        // Kích hoạt hiệu ứng reload lại component
        bannerEl.classList.remove("is-updated");
        void bannerEl.offsetWidth; // Force reflow để chạy lại animation
        bannerEl.classList.add("is-updated");

        bannerStoreTitle.textContent =
          state.selectedStore.name +
          (state.selectedStore.address
            ? " - " + state.selectedStore.address
            : "");
        if (hiddenStoreInput) {
          hiddenStoreInput.value = state.selectedStore.id;
        }

        if (state.selectedStore.distance) {
          bannerDistanceWrap.style.display = "inline-flex";
          bannerDistanceText.textContent = state.selectedStore.distance.text;
          bannerDurationText.textContent = state.selectedStore.duration
            ? t(
                "gps_travel_time",
                `(~${state.selectedStore.duration.text} di chuyển)`,
                { time: state.selectedStore.duration.text },
              )
            : "";

          // Tính số km từ Goong Maps API (distance.value là mét -> chia 1000 để ra km)
          const distanceKm = (state.selectedStore.distance.value || 0) / 1000;
          const isFreeship = distanceKm <= 3.0;

          // In test console log để dễ theo dõi
          console.log(
            `[Goong Map] Khoảng cách: ${distanceKm.toFixed(2)} km -> ${isFreeship ? "Miễn phí giao hàng (Freeship)" : "Tính phí theo Grab"}`,
          );

          // Cập nhật nhãn phí ship trên Banner chi nhánh
          if (bannerShippingText) {
            bannerShippingText.style.display = "inline-flex";
            bannerShippingText.className =
              "location-banner-shipping " +
              (isFreeship ? "is-free" : "is-grab");
            bannerShippingText.innerHTML = isFreeship
              ? `🎉 ${t("shipping_fee_freeship", "Miễn phí giao hàng (Freeship ≤ 3km)")}`
              : `🛵 ${t("shipping_fee_grab", "Tính phí theo giá Grab (> 3km)")}`;
          }

          // Cập nhật dòng phí giao hàng trong Bảng tổng tiền đơn hàng (Order Review)
          if (reviewShippingWrap && reviewShippingText) {
            reviewShippingWrap.style.display = "flex";
            reviewShippingText.className = isFreeship
              ? "shipping-text-free"
              : "shipping-text-grab";
            reviewShippingText.textContent = isFreeship
              ? t(
                  "shipping_fee_freeship",
                  "Miễn phí giao hàng (Freeship ≤ 3km)",
                )
              : t("shipping_fee_grab", "Tính phí theo giá Grab (> 3km)");
          }
        } else {
          bannerDistanceWrap.style.display = "none";
          if (bannerShippingText) bannerShippingText.style.display = "none";
          if (reviewShippingWrap) reviewShippingWrap.style.display = "none";
        }

        // Hiển thị lý do vì sao chọn chi nhánh này
        const isNearest =
          state.nearestStore &&
          Number(state.nearestStore.id) === Number(state.selectedStore.id);
        if (bannerReasonWrap && bannerReasonText) {
          bannerReasonWrap.style.display = "block";
          if (isNearest && state.selectedStore.distance) {
            const reasonTitle = t("gps_reason_selected", "Lý do chọn:");
            const reasonDesc = t(
              "gps_reason_nearest_desc",
              "Chi nhánh có khoảng cách gần bạn nhất (chỉ <strong>{distance}</strong>), giúp giao nhanh & giữ lạnh đồ uống tốt nhất.",
              { distance: state.selectedStore.distance.text },
            );
            bannerReasonText.innerHTML = `📍 <strong>${reasonTitle}</strong> ${reasonDesc}`;
          } else if (!isNearest) {
            const manualTitle = t(
              "gps_manual_choice",
              "Đổi theo lựa chọn của bạn:",
            );
            const manualDesc = t(
              "gps_reason_manual_desc",
              "Chi nhánh cách vị trí nhận <strong>{distance}</strong>.",
              {
                distance: state.selectedStore.distance
                  ? state.selectedStore.distance.text
                  : "N/A",
              },
            );
            bannerReasonText.innerHTML = `👉 <strong>${manualTitle}</strong> ${manualDesc}`;
          } else {
            bannerReasonWrap.style.display = "none";
          }
        }
      } else {
        bannerEl.classList.remove("is-loading");
        if (bannerIcon) bannerIcon.classList.remove("is-spinning");
        if (bannerReasonWrap) bannerReasonWrap.style.display = "none";
        if (bannerShippingText) bannerShippingText.style.display = "none";
        if (reviewShippingWrap) reviewShippingWrap.style.display = "none";
        if (btnChangeStore) {
          btnChangeStore.disabled = false;
          btnChangeStore.style.opacity = "1";
          btnChangeStore.textContent = t("gps_change_store", "Đổi chi nhánh");
        }
        bannerStoreTitle.textContent = t(
          "gps_auto_detecting",
          "Đang tự động chọn chi nhánh gần nhất...",
        );
        bannerDistanceWrap.style.display = "none";
      }

      renderStoreModalList(state.storesWithDistance, state.selectedStore);
    });

    // DISABLED: dùng JSON cục bộ (nút GPS gọi Geolocation + Goong Reverse Geocode)
    /*
    if (btnGps) {
      btnGps.addEventListener('click', async (e) => {
        e.preventDefault();
        const textSpan = btnGps.querySelector('.btn-gps-text');
        btnGps.classList.add('is-detecting');
        if (textSpan) {
          textSpan.textContent = t('gps_locating', 'Đang định vị...');
        }

        // Lập tức reload lại component sang trạng thái loading
        manager.updateState({ isLoading: true, selectedStore: null });

        try {
          const coords = await manager.resolveUserCoordinates({ method: 'gps' });
          const resolvedAddr = manager.getState().userAddress || '';
          if (addressInput) {
            addressInput.value = resolvedAddr;
          }
          if (resolvedAddr) {
            setAddressVerified(resolvedAddr);
          }
          if (apiKey) {
            await manager.calculateNearestStore(coords);
          }
        } catch (err) {
          manager.updateState({ isLoading: false });
          alert(err.message || 'Không thể lấy vị trí GPS.');
        } finally {
          btnGps.classList.remove('is-detecting');
          if (textSpan) {
            textSpan.textContent = t('gps_use_current', 'Dùng vị trí hiện tại');
          }
        }
      });
    }

    // AutoComplete Debounced Search gọi Goong Maps API
    if (addressInput && autocompleteDropdown && apiKey) {
      let activeController = null;

      const debouncedSearch = LocationOrderManager.debounce(async (val) => {
        if (activeController) activeController.abort();
        activeController = new AbortController();

        try {
          const predictions = await goong.placeAutoComplete(val, activeController.signal);
          renderPredictions(predictions);
        } catch (err) {
          if (err.name !== 'AbortError') console.warn(err);
        }
      }, 400);

      addressInput.addEventListener('input', (e) => {
        const val = e.target.value.trim();
        if (val !== verifiedAddressText) {
          setAddressUnverified(t('gps_address_changed_warning', 'Bạn vừa thay đổi địa chỉ. Bắt buộc phải bấm chọn một gợi ý trong danh sách bên dưới hoặc bấm nút "Dùng vị trí hiện tại".'));
          manager.updateState({
            isLoading: true,
            selectedStore: null,
            userAddress: val
          });
        }
        if (val.length < 3) {
          autocompleteDropdown.classList.remove('is-open');
          autocompleteDropdown.innerHTML = '';
          return;
        }
        debouncedSearch(val);
      });

      addressInput.addEventListener('blur', () => {
        setTimeout(() => {
          if (!isAddressVerified && addressInput.value.trim().length > 0) {
            addressInput.classList.add('has-location-error');
            setAddressUnverified(t('gps_select_hint', 'Vui lòng bấm chọn một địa chỉ cụ thể từ danh sách gợi ý hoặc bấm nút "Dùng vị trí hiện tại" để xác định chi nhánh.'));
          }
        }, 220);
      });

      document.addEventListener('click', (e) => {
        if (!autocompleteDropdown.contains(e.target) && e.target !== addressInput) {
          autocompleteDropdown.classList.remove('is-open');
        }
      });

      function renderPredictions(predictions) {
        if (!predictions || predictions.length === 0) {
          autocompleteDropdown.classList.remove('is-open');
          autocompleteDropdown.innerHTML = '';
          return;
        }

        autocompleteDropdown.innerHTML = predictions
          .map(
            (p) => `
            <div class="autocomplete-item" data-place-id="${p.place_id}" data-desc="${p.description}">
              <span class="autocomplete-item-icon">📍</span>
              <span>${p.description}</span>
            </div>
          `
          )
          .join('');

        autocompleteDropdown.classList.add('is-open');

        autocompleteDropdown.querySelectorAll('.autocomplete-item').forEach((item) => {
          item.addEventListener('click', async () => {
            const placeId = item.getAttribute('data-place-id');
            const desc = item.getAttribute('data-desc');
            addressInput.value = desc;
            setAddressVerified(desc);
            autocompleteDropdown.classList.remove('is-open');

            manager.updateState({ isLoading: true, selectedStore: null });

            try {
              const coords = await manager.resolveUserCoordinates({
                method: 'place_id',
                placeId,
                description: desc,
              });
              await manager.calculateNearestStore(coords);
            } catch (err) {
              manager.updateState({ isLoading: false });
              console.error(err);
            }
          });
        });
      }
    }

    async function handleAddressLookup(text) {
      if (!apiKey || !text || text.length < 4) return false;
      try {
        const coords = await manager.resolveUserCoordinates({
          method: 'manual_address',
          address: text,
        });
        await manager.calculateNearestStore(coords);
        return true;
      } catch (e) {
        console.warn('[Location] Geocoding lookup warning:', e.message);
        return false;
      }
    }

    if (checkoutForm && apiKey) {
      checkoutForm.addEventListener('submit', (e) => {
        if (!isAddressVerified) {
          e.preventDefault();
          e.stopPropagation();

          if (addressInput) {
            addressInput.classList.add('has-location-error');
            addressInput.focus();

            const currentVal = addressInput.value.trim();
            if (currentVal.length >= 3 && autocompleteDropdown) {
              goong.placeAutoComplete(currentVal).then((preds) => {
                if (preds && preds.length > 0) {
                  autocompleteDropdown.innerHTML = preds
                    .map(
                      (p) => `
                      <div class="autocomplete-item" data-place-id="${p.place_id}" data-desc="${p.description}">
                        <span class="autocomplete-item-icon">📍</span>
                        <span>${p.description}</span>
                      </div>
                    `
                    )
                    .join('');
                  autocompleteDropdown.classList.add('is-open');

                  autocompleteDropdown.querySelectorAll('.autocomplete-item').forEach((item) => {
                    item.addEventListener('click', async () => {
                      const placeId = item.getAttribute('data-place-id');
                      const desc = item.getAttribute('data-desc');
                      addressInput.value = desc;
                      setAddressVerified(desc);
                      autocompleteDropdown.classList.remove('is-open');

                      try {
                        const coords = await manager.resolveUserCoordinates({
                          method: 'place_id',
                          placeId,
                          description: desc,
                        });
                        await manager.calculateNearestStore(coords);
                      } catch (err) {
                        console.error(err);
                      }
                    });
                  });
                }
              }).catch(() => {});
            }
          }

          setAddressUnverified(t('gps_submit_required_warning', 'Bắt buộc phải bấm chọn một địa chỉ cụ thể từ danh sách gợi ý bên dưới hoặc bấm nút "Dùng vị trí hiện tại" để hệ thống tính chi nhánh phục vụ gần nhất!'));
          if (addressWarning) {
            addressWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          return false;
        }
      }, true);
    }
    */

    // ========================================================
    // TÌM KIẾM ĐỊA CHỈ 2 CẤP CỤC BỘ TỪ FILE JSON (Shopee Style)
    // ========================================================
    function normalizeVietnamese(str) {
      if (!str) return "";
      return str
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d")
        .replace(/Đ/g, "d")
        .toLowerCase()
        .trim();
    }

    const cascadeWrap = document.querySelector("[data-address-cascade]");
    if (cascadeWrap) {
      const jsonUrl =
        cascadeWrap.getAttribute("data-json-url") ||
        "/assets/data/vietnam-provinces.json";
      const savedAddress = (
        cascadeWrap.getAttribute("data-saved-address") || ""
      ).trim();
      const hasSaved =
        cascadeWrap.getAttribute("data-has-saved") === "1" &&
        savedAddress.length > 0;
      const modeRadios = document.querySelectorAll("[data-mode-radio]");
      const btnEditSaved = document.querySelector("[data-btn-edit-saved]");
      const deliveryAddressAlias = document.getElementById(
        "delivery-address-alias",
      );

      const provinceInput = document.getElementById("address-province-input");
      const provinceDropdown = cascadeWrap.querySelector(
        "[data-province-dropdown]",
      );
      const provinceCodeHidden = cascadeWrap.querySelector(
        "[data-province-code]",
      );
      const provinceNameHidden = cascadeWrap.querySelector(
        "[data-province-name]",
      );

      const wardInput = document.getElementById("address-ward-input");
      const wardDropdown = cascadeWrap.querySelector("[data-ward-dropdown]");
      const wardCodeHidden = cascadeWrap.querySelector("[data-ward-code]");
      const wardNameHidden = cascadeWrap.querySelector("[data-ward-name]");

      const streetInput = cascadeWrap.querySelector("[data-street-address]");
      const fullAddressField = document.getElementById(
        "delivery-address-field",
      );
      const btnFindStore = cascadeWrap.querySelector("[data-btn-find-store]");
      let isFindingStore = false;
      let lastCalculatedAddress = "";

      let provincesData = null;
      let selectedProvinceObj = null;
      let selectedWardObj = null;

      function getCurrentAddressMode() {
        if (!hasSaved) return "new";
        const checked = document.querySelector(
          'input[name="address_mode"]:checked',
        );
        return checked ? checked.value : "saved";
      }

      function setAddressMode(mode, options = {}) {
        const isSaved = mode === "saved" && hasSaved;
        modeRadios.forEach((r) => {
          r.checked = r.value === (isSaved ? "saved" : "new");
        });
        document.querySelectorAll(".address-mode-card").forEach((card) => {
          const radio = card.querySelector('input[name="address_mode"]');
          if (radio && radio.checked) {
            card.classList.add("is-selected");
          } else {
            card.classList.remove("is-selected");
          }
        });

        if (isSaved) {
          cascadeWrap.style.display = "none";
          if (fullAddressField) {
            fullAddressField.value = savedAddress;
            fullAddressField.removeAttribute("required");
          }
          if (deliveryAddressAlias) {
            deliveryAddressAlias.value = savedAddress;
          }
          if (options.autoConfirm !== false) {
            findNearestBranchForAddress(savedAddress);
          }
        } else {
          cascadeWrap.style.display = "";
          if (fullAddressField) {
            fullAddressField.setAttribute("required", "required");
          }
          syncFullDeliveryAddress();
        }
      }

      function parseAddressIntoCascade(rawAddress) {
        if (!rawAddress) return;
        const raw = rawAddress.trim();
        const normRaw = normalizeVietnamese(raw);

        if (!provincesData || !Array.isArray(provincesData)) {
          if (streetInput) {
            streetInput.value = raw;
            streetInput.classList.remove("has-location-error");
          }
          syncFullDeliveryAddress();
          return;
        }

        let matchedProv = null;
        const sortedProvs = [...provincesData].sort(
          (a, b) => b.name.length - a.name.length,
        );
        for (const p of sortedProvs) {
          const normName = normalizeVietnamese(p.name);
          const cleanName = normName.replace(/^(tinh|thanh pho|tp)\s+/, "");
          if (normRaw.includes(cleanName) || normRaw.includes(normName)) {
            matchedProv = p;
            break;
          }
        }

        let matchedWard = null;
        if (matchedProv) {
          selectedProvinceObj = matchedProv;
          if (provinceInput) {
            provinceInput.value = matchedProv.name;
            provinceInput.classList.remove("has-location-error");
            provinceInput.classList.add("is-location-verified");
          }
          if (provinceCodeHidden) provinceCodeHidden.value = matchedProv.code;
          if (provinceNameHidden) provinceNameHidden.value = matchedProv.name;

          if (wardInput) {
            wardInput.disabled = false;
            wardInput.placeholder = t(
              "addr_ward_placeholder",
              "Nhập tìm Phường / Xã...",
            );
          }

          if (Array.isArray(matchedProv.wards)) {
            const sortedWards = [...matchedProv.wards].sort(
              (a, b) => b.name.length - a.name.length,
            );
            for (const w of sortedWards) {
              const normWName = normalizeVietnamese(w.name);
              const cleanWName = normWName.replace(
                /^(phuong|xa|thi tran)\s+/,
                "",
              );
              if (normRaw.includes(cleanWName) || normRaw.includes(normWName)) {
                matchedWard = w;
                break;
              }
            }
          }

          if (matchedWard) {
            selectedWardObj = matchedWard;
            if (wardInput) {
              wardInput.value = matchedWard.name;
              wardInput.classList.remove("has-location-error");
              wardInput.classList.add("is-location-verified");
            }
            if (wardCodeHidden) wardCodeHidden.value = matchedWard.code;
            if (wardNameHidden) wardNameHidden.value = matchedWard.name;
          } else {
            selectedWardObj = null;
            if (wardInput) {
              wardInput.value = "";
              wardInput.classList.remove("is-location-verified");
            }
            if (wardCodeHidden) wardCodeHidden.value = "";
            if (wardNameHidden) wardNameHidden.value = "";
          }

          const parts = raw
            .split(",")
            .map((s) => s.trim())
            .filter(Boolean);
          const nonMatchedParts = [];
          for (const part of parts) {
            const normPart = normalizeVietnamese(part);
            const isProv = normPart.includes(
              normalizeVietnamese(matchedProv.name).replace(
                /^(tinh|thanh pho|tp)\s+/,
                "",
              ),
            );
            const isWard =
              matchedWard &&
              normPart.includes(
                normalizeVietnamese(matchedWard.name).replace(
                  /^(phuong|xa|thi tran)\s+/,
                  "",
                ),
              );
            if (!isProv && !isWard) {
              nonMatchedParts.push(part);
            }
          }
          if (streetInput) {
            streetInput.value =
              nonMatchedParts.length > 0
                ? nonMatchedParts.join(", ")
                : parts[0] || "";
            streetInput.classList.remove("has-location-error");
          }
        } else {
          if (streetInput) {
            streetInput.value = raw;
            streetInput.classList.remove("has-location-error");
          }
        }

        syncFullDeliveryAddress();
      }

      // 1. Tải JSON một lần và cache ở client
      fetch(jsonUrl)
        .then((res) => {
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return res.json();
        })
        .then((data) => {
          if (Array.isArray(data)) {
            provincesData = data;
            console.log(
              "[Address Cascade] Đã nạp thành công:",
              provincesData.length,
              "tỉnh thành.",
            );

            // Nếu người dùng đang focus ô Tỉnh/Thành thì mở ngay dropdown
            if (document.activeElement === provinceInput && provinceDropdown) {
              const q = normalizeVietnamese(provinceInput.value.trim());
              const items = q
                ? provincesData.filter((p) =>
                    normalizeVietnamese(p.name).includes(q),
                  )
                : provincesData;
              provinceDropdown.innerHTML = items
                .map(
                  (item, idx) =>
                    `<div class="autocomplete-item" data-idx="${idx}" data-code="${item.code}"><span class="autocomplete-item-icon">📍</span><span>${item.name}</span></div>`,
                )
                .join("");
              provinceDropdown.classList.add("is-open");
            }

            // Khôi phục giá trị đã chọn nếu có dữ liệu cũ từ draft/session
            const savedProvCode = provinceCodeHidden
              ? provinceCodeHidden.value
              : "";
            if (savedProvCode) {
              const matched = provincesData.find(
                (p) => p.code === savedProvCode,
              );
              if (matched) {
                selectedProvinceObj = matched;
                if (provinceInput) {
                  provinceInput.value = matched.name;
                  provinceInput.classList.add("is-location-verified");
                }
                if (wardInput) {
                  wardInput.disabled = false;
                  wardInput.placeholder = t(
                    "addr_ward_placeholder",
                    "Nhập tìm Phường / Xã...",
                  );
                }
                const savedWardCode = wardCodeHidden
                  ? wardCodeHidden.value
                  : "";
                if (savedWardCode && Array.isArray(matched.wards)) {
                  const matchedWard = matched.wards.find(
                    (w) => w.code === savedWardCode,
                  );
                  if (matchedWard) {
                    selectedWardObj = matchedWard;
                    if (wardInput) {
                      wardInput.value = matchedWard.name;
                      wardInput.classList.add("is-location-verified");
                    }
                  }
                }
              }
            }
          }
        })
        .catch((err) => {
          console.error("[Address Cascade] Lỗi tải file JSON tỉnh thành:", err);
          if (addressWarning) {
            addressWarning.textContent = t(
              "addr_err_load_provinces",
              "⚠️ Không thể tải danh sách tỉnh thành cục bộ. Vui lòng thử tải lại trang!",
            );
            addressWarning.classList.add("is-visible");
          }
        });

      let activeBranchSearchPromise = null;

      // Tìm chi nhánh gần nhất có áp dụng Client Cache & Fallback thông minh
      async function findNearestBranchForAddress(customAddress) {
        if (activeBranchSearchPromise) {
          return await activeBranchSearchPromise;
        }

        activeBranchSearchPromise = (async () => {
          if (storesPromise) {
            await storesPromise;
          }
          const isSavedMode = getCurrentAddressMode() === "saved";
          const provName = provinceNameHidden
            ? provinceNameHidden.value.trim()
            : "";
          const wardName = wardNameHidden ? wardNameHidden.value.trim() : "";
          const street = streetInput ? streetInput.value.trim() : "";
          const fullStr = (
            customAddress ||
            (isSavedMode
              ? savedAddress
              : [street, wardName, provName].filter(Boolean).join(", "))
          ).trim();

          if (!fullStr) {
            setAddressUnverified(
              t("addr_err_empty", "Vui lòng nhập địa chỉ giao hàng!"),
            );
            return false;
          }

          if (!isSavedMode && (!provName || !wardName || !street)) {
            setAddressUnverified(
              t(
                "addr_err_incomplete",
                "Vui lòng nhập đầy đủ Tỉnh/Thành, Phường/Xã và Số nhà trước khi tìm chi nhánh!",
              ),
            );
            return false;
          }

          if (btnFindStore) {
            btnFindStore.disabled = true;
            const textEl = btnFindStore.querySelector(".btn-find-text");
            if (textEl)
              textEl.textContent = t(
                "addr_btn_finding",
                "Đang tìm kiếm chi nhánh tối ưu...",
              );
          }

          manager.updateState({ isLoading: true, selectedStore: null });

          try {
            // KỸ THUẬT 1: KIỂM TRA CACHE TRONG SESSIONSTORAGE (0 API call nếu đã tìm địa chỉ này)
            const cacheKey = "hds_geo_" + encodeURIComponent(fullStr);
            let coords = null;
            try {
              const cached = sessionStorage.getItem(cacheKey);
              if (cached) {
                coords = JSON.parse(cached);
                console.log(
                  "[Goong Map] Sử dụng tọa độ từ Cache (0 API request):",
                  coords,
                );
              }
            } catch (e) {}

            // Nếu chưa có trong cache thì mới gọi Geocode qua proxy
            if (!coords && goong && goong.endpoint) {
              try {
                // Thử Geocode địa chỉ cụ thể đầy đủ
                const geo = await goong.forwardGeocode(fullStr);
                coords = geo.coordinates;
              } catch (err1) {
                console.warn(
                  "[Geocode] Không tìm thấy số nhà cụ thể, thử tìm theo Phường/Xã:",
                  err1.message,
                );
                // Fallback: Geocode theo Phường/Xã + Tỉnh/Thành
                if (wardName && provName) {
                  const fallbackQuery = `${wardName}, ${provName}`;
                  const geoFallback = await goong.forwardGeocode(fallbackQuery);
                  coords = geoFallback.coordinates;
                }
              }

              // Lưu tọa độ vào cache để lần sau không tốn request
              if (coords) {
                try {
                  sessionStorage.setItem(cacheKey, JSON.stringify(coords));
                } catch (e) {}
              }
            }

            if (coords) {
              await manager.calculateNearestStore(coords);
              lastCalculatedAddress = fullStr;
              setAddressVerified(fullStr);
              if (btnFindStore) {
                const textEl = btnFindStore.querySelector(".btn-find-text");
                if (textEl)
                  textEl.textContent = t(
                    "addr_btn_confirmed",
                    "✅ Đã xác định chi nhánh phục vụ",
                  );
              }
              return true;
            } else {
              // Không có Goong Key hoặc không tìm thấy tọa độ -> Chọn chi nhánh đầu tiên mặc định
              const stores = manager.getState().storesWithDistance;
              if (stores && stores.length > 0) {
                manager.selectStore(stores[0].id);
                setAddressVerified(fullStr);
                return true;
              }
              throw new Error(
                t(
                  "addr_err_saved_unreachable",
                  "Không thể xác định chi nhánh phục vụ cho địa chỉ này.",
                ),
              );
            }
          } catch (err) {
            console.error("[Location] Lỗi tìm chi nhánh:", err);
            manager.updateState({ isLoading: false });
            if (isSavedMode) {
              setAddressUnverified(
                t(
                  "addr_err_saved_unreachable",
                  "Không thể xác định chi nhánh phục vụ cho địa chỉ đã lưu. Vui lòng kiểm tra lại!",
                ),
              );
              setAddressMode("new", { autoConfirm: false });
              parseAddressIntoCascade(savedAddress);
            } else {
              const stores = manager.getState().storesWithDistance;
              if (
                stores &&
                stores.length > 0 &&
                !manager.getState().selectedStore
              ) {
                manager.selectStore(stores[0].id);
              }
              setAddressVerified(fullStr);
            }
          } finally {
            if (btnFindStore) {
              btnFindStore.disabled = false;
              const textEl = btnFindStore.querySelector(".btn-find-text");
              if (textEl && !manager.getState().selectedStore) {
                textEl.textContent = t("addr_btn_confirm", "Xác nhận địa chỉ");
              }
            }
          }
          return false;
        })();

        try {
          return await activeBranchSearchPromise;
        } finally {
          activeBranchSearchPromise = null;
        }
      }

      if (btnFindStore) {
        btnFindStore.addEventListener("click", (e) => {
          e.preventDefault();
          findNearestBranchForAddress();
        });
      }

      if (hasSaved && getCurrentAddressMode() === "saved") {
        if (fullAddressField) {
          fullAddressField.value = savedAddress;
          fullAddressField.removeAttribute("required");
        }
        if (deliveryAddressAlias) {
          deliveryAddressAlias.value = savedAddress;
        }
        storesPromise.then(() => {
          if (getCurrentAddressMode() === "saved") {
            findNearestBranchForAddress(savedAddress);
          }
        });
      }

      modeRadios.forEach((radio) => {
        radio.addEventListener("change", () => {
          setAddressMode(radio.value);
        });
      });

      if (btnEditSaved) {
        btnEditSaved.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          setAddressMode("new", { autoConfirm: false });
          parseAddressIntoCascade(savedAddress);
          if (streetInput) {
            streetInput.focus();
          }
        });
      }

      // ========================================================
      // TÍNH NĂNG GPS: LẤY VỊ TRÍ HIỆN TẠI & TỰ ĐỘNG KHỚP TỈNH / PHƯỜNG
      // ========================================================
      const btnGps =
        cascadeWrap.querySelector("[data-btn-gps]") ||
        document.querySelector("[data-btn-gps]");
      if (btnGps) {
        btnGps.addEventListener("click", async (e) => {
          e.preventDefault();
          const textSpan = btnGps.querySelector(".btn-gps-text");
          btnGps.classList.add("is-detecting");
          if (textSpan) {
            textSpan.textContent = t("gps_locating", "Đang định vị...");
          }

          // Kích hoạt trạng thái loading trên Store Banner
          manager.updateState({ isLoading: true, selectedStore: null });

          try {
            // 1. Lấy tọa độ từ thiết bị qua Geolocation API
            const coords = await manager.getGPSLocation();

            // 2. Tra cứu địa chỉ chi tiết qua Goong Reverse Geocoding qua proxy
            let resolvedAddr = "";
            let compound = null;
            if (goong && goong.endpoint) {
              try {
                const rev = await goong.reverseGeocode(coords.lat, coords.lng);
                resolvedAddr = rev.formattedAddress || "";
                compound = rev.compound || null;
              } catch (revErr) {
                console.warn("[GPS] Lỗi reverse geocode:", revErr.message);
              }
            }

            // 3. Tự động nhận diện và gán Tỉnh / Phường vào dropdown cascade
            if (provincesData && Array.isArray(provincesData)) {
              let matchedProv = null;
              if (compound && compound.province) {
                const normTarget = normalizeVietnamese(compound.province);
                matchedProv = provincesData.find((p) => {
                  const np = normalizeVietnamese(p.name);
                  return np.includes(normTarget) || normTarget.includes(np);
                });
              }
              if (!matchedProv && resolvedAddr) {
                const normAddr = normalizeVietnamese(resolvedAddr);
                matchedProv = provincesData.find((p) =>
                  normAddr.includes(normalizeVietnamese(p.name)),
                );
              }
              if (matchedProv) {
                selectedProvinceObj = matchedProv;
                if (provinceInput) {
                  provinceInput.value = matchedProv.name;
                  provinceInput.classList.remove("has-location-error");
                  provinceInput.classList.add("is-location-verified");
                }
                if (provinceCodeHidden)
                  provinceCodeHidden.value = matchedProv.code;
                if (provinceNameHidden)
                  provinceNameHidden.value = matchedProv.name;

                if (wardInput) {
                  wardInput.disabled = false;
                  wardInput.placeholder = t(
                    "addr_ward_placeholder",
                    "Nhập tìm Phường / Xã...",
                  );
                }

                let matchedWard = null;
                if (Array.isArray(matchedProv.wards)) {
                  if (compound && compound.commune) {
                    const normCommune = normalizeVietnamese(compound.commune);
                    matchedWard = matchedProv.wards.find((w) => {
                      const nw = normalizeVietnamese(w.name);
                      if (
                        nw === normCommune ||
                        nw.includes(normCommune) ||
                        normCommune.includes(nw)
                      ) {
                        return true;
                      }
                      if (Array.isArray(w.old_names)) {
                        return w.old_names.some((old) => {
                          const nOld = normalizeVietnamese(old);
                          return (
                            nOld === normCommune ||
                            nOld.includes(normCommune) ||
                            normCommune.includes(nOld)
                          );
                        });
                      }
                      return false;
                    });
                  }
                  if (!matchedWard && resolvedAddr) {
                    const normAddr = normalizeVietnamese(resolvedAddr);
                    const sortedWards = [...matchedProv.wards].sort(
                      (a, b) => b.name.length - a.name.length,
                    );
                    matchedWard = sortedWards.find((w) => {
                      if (normAddr.includes(normalizeVietnamese(w.name))) {
                        return true;
                      }
                      if (Array.isArray(w.old_names)) {
                        return w.old_names.some((old) =>
                          normAddr.includes(normalizeVietnamese(old)),
                        );
                      }
                      return false;
                    });
                  }
                }
                if (matchedWard) {
                  selectedWardObj = matchedWard;
                  if (wardInput) {
                    wardInput.value = matchedWard.name;
                    wardInput.classList.remove("has-location-error");
                    wardInput.classList.add("is-location-verified");
                  }
                  if (wardCodeHidden) wardCodeHidden.value = matchedWard.code;
                  if (wardNameHidden) wardNameHidden.value = matchedWard.name;
                }
              }
            }

            // Gán số nhà/đường
            if (streetInput) {
              let streetVal = "";
              if (compound && compound.name) {
                streetVal = compound.name;
              } else if (resolvedAddr) {
                const parts = resolvedAddr.split(",");
                streetVal = parts[0] ? parts[0].trim() : resolvedAddr;
              }
              if (streetVal) {
                streetInput.value = streetVal;
                streetInput.classList.remove("has-location-error");
              }
            }

            // 4. Tính chi nhánh gần nhất và phí giao hàng dựa trên tọa độ GPS thực tế
            await manager.calculateNearestStore(coords);

            // 5. Đồng bộ địa chỉ và đánh dấu đã xác thực
            syncFullDeliveryAddress();
            const fullStr = fullAddressField
              ? fullAddressField.value.trim()
              : resolvedAddr || "";
            if (fullStr) {
              lastCalculatedAddress = fullStr;
              setAddressVerified(fullStr);
              try {
                sessionStorage.setItem(
                  "hds_geo_" + encodeURIComponent(fullStr),
                  JSON.stringify(coords),
                );
              } catch (e) {}
            }

            if (btnFindStore) {
              const btnText = btnFindStore.querySelector(".btn-find-text");
              if (btnText)
                btnText.textContent = t(
                  "addr_btn_confirmed",
                  "✅ Đã xác định chi nhánh phục vụ",
                );
            }
          } catch (err) {
            console.error("[GPS] Lỗi lấy vị trí hiện tại:", err);
            manager.updateState({ isLoading: false });
            alert(
              err.message ||
                t(
                  "gps_permission_denied",
                  "Không thể lấy vị trí GPS hiện tại. Vui lòng chọn địa chỉ bằng tay.",
                ),
            );
          } finally {
            btnGps.classList.remove("is-detecting");
            if (textSpan) {
              textSpan.textContent = t(
                "gps_use_current",
                "Dùng vị trí hiện tại",
              );
            }
          }
        });
      }

      // Đồng bộ vào trường address đầy đủ để server lưu vào bảng đơn hàng
      function syncFullDeliveryAddress() {
        const provName = provinceNameHidden
          ? provinceNameHidden.value.trim()
          : "";
        const wardName = wardNameHidden ? wardNameHidden.value.trim() : "";
        const street = streetInput ? streetInput.value.trim() : "";

        const parts = [];
        if (street) parts.push(street);
        if (wardName) parts.push(wardName);
        if (provName) parts.push(provName);

        const fullStr = parts.join(", ");
        if (fullAddressField) {
          fullAddressField.value = fullStr;
        }

        const isReadyToFind = Boolean(
          provName && wardName && street && street.length >= 2,
        );
        if (btnFindStore) {
          btnFindStore.disabled = !isReadyToFind;
          if (isReadyToFind) {
            btnFindStore.classList.add("is-ready");
          } else {
            btnFindStore.classList.remove("is-ready");
          }
          if (fullStr !== lastCalculatedAddress) {
            const textEl = btnFindStore.querySelector(".btn-find-text");
            if (textEl)
              textEl.textContent = t("addr_btn_confirm", "Xác nhận địa chỉ");
          }
        }

        if (provName && wardName && street) {
          if (manager.getState().selectedStore) {
            setAddressVerified(fullStr);
          }
        } else {
          setAddressUnverified();
        }
      }

      if (streetInput) {
        streetInput.addEventListener("input", () => {
          if (streetInput.value.trim().length > 0) {
            streetInput.classList.remove("has-location-error");
          }
          syncFullDeliveryAddress();
        });
      }

      // Helper thiết lập tìm kiếm gợi ý có keyboard navigation (mũi tên, Enter, Esc)
      function setupAutocompleteSearch({
        inputEl,
        dropdownEl,
        getItemsFn,
        onSelectFn,
        placeholderNoResult = "Không tìm thấy kết quả phù hợp",
      }) {
        let activeIdx = -1;
        let currentResults = [];

        function renderDropdown(items) {
          currentResults = items;
          activeIdx = -1;

          if (!items || items.length === 0) {
            dropdownEl.innerHTML = `<div class="autocomplete-item autocomplete-no-result">${placeholderNoResult}</div>`;
            dropdownEl.classList.add("is-open");
            return;
          }

          dropdownEl.innerHTML = items
            .map(
              (item, idx) => `
              <div class="autocomplete-item" data-idx="${idx}" data-code="${item.code}">
                <span class="autocomplete-item-icon">📍</span>
                <span>${item.name}</span>
              </div>
            `,
            )
            .join("");
          dropdownEl.classList.add("is-open");

          dropdownEl.querySelectorAll(".autocomplete-item").forEach((div) => {
            div.addEventListener("click", () => {
              const idx = Number(div.getAttribute("data-idx"));
              const selectedItem = currentResults[idx];
              if (selectedItem) {
                onSelectFn(selectedItem);
                closeDropdown();
              }
            });
          });
        }

        function closeDropdown() {
          dropdownEl.classList.remove("is-open");
          dropdownEl.innerHTML = "";
          activeIdx = -1;
          currentResults = [];
        }

        function highlightItem(newIdx) {
          const items = dropdownEl.querySelectorAll(
            ".autocomplete-item:not(.autocomplete-no-result)",
          );
          if (!items.length) return;

          items.forEach((it) => it.classList.remove("is-active"));
          activeIdx = (newIdx + items.length) % items.length;
          const target = items[activeIdx];
          if (target) {
            target.classList.add("is-active");
            target.scrollIntoView({ block: "nearest" });
          }
        }

        // Debounce 120ms khi gõ
        const debouncedFilter = LocationOrderManager.debounce((val) => {
          const items = getItemsFn(val);
          renderDropdown(items);
        }, 120);

        inputEl.addEventListener("input", (e) => {
          inputEl.classList.remove("is-location-verified");
          debouncedFilter(e.target.value.trim());
        });

        inputEl.addEventListener("focus", () => {
          const items = getItemsFn(inputEl.value.trim());
          renderDropdown(items);
        });

        inputEl.addEventListener("click", () => {
          if (!dropdownEl.classList.contains("is-open")) {
            const items = getItemsFn(inputEl.value.trim());
            renderDropdown(items);
          }
        });

        // Điều hướng bằng phím mũi tên, Enter, Escape
        inputEl.addEventListener("keydown", (e) => {
          if (!dropdownEl.classList.contains("is-open")) return;

          if (e.key === "ArrowDown") {
            e.preventDefault();
            highlightItem(activeIdx + 1);
          } else if (e.key === "ArrowUp") {
            e.preventDefault();
            highlightItem(activeIdx - 1);
          } else if (e.key === "Enter") {
            e.preventDefault();
            if (activeIdx >= 0 && currentResults[activeIdx]) {
              onSelectFn(currentResults[activeIdx]);
              closeDropdown();
            } else if (currentResults.length === 1) {
              onSelectFn(currentResults[0]);
              closeDropdown();
            }
          } else if (e.key === "Escape") {
            closeDropdown();
          }
        });

        // Blur: chỉ chấp nhận giá trị CHỌN từ danh sách
        inputEl.addEventListener("blur", () => {
          setTimeout(() => {
            closeDropdown();
          }, 200);
        });

        // Click ra ngoài đóng dropdown
        document.addEventListener("click", (e) => {
          if (!dropdownEl.contains(e.target) && e.target !== inputEl) {
            closeDropdown();
          }
        });

        return { closeDropdown };
      }

      // --- CẤU HÌNH BƯỚC 1: TỈNH / THÀNH PHỐ ---
      if (provinceInput && provinceDropdown) {
        setupAutocompleteSearch({
          inputEl: provinceInput,
          dropdownEl: provinceDropdown,
          placeholderNoResult: t(
            "addr_no_prov_result",
            "Không tìm thấy Tỉnh / Thành phố phù hợp",
          ),
          getItemsFn: (searchVal) => {
            if (!provincesData) return [];
            const q = normalizeVietnamese(searchVal);
            if (!q) return provincesData;
            return provincesData.filter((p) =>
              normalizeVietnamese(p.name).includes(q),
            );
          },
          onSelectFn: (prov) => {
            const isChanged =
              !selectedProvinceObj || selectedProvinceObj.code !== prov.code;
            selectedProvinceObj = prov;
            provinceInput.value = prov.name;
            provinceInput.classList.remove("has-location-error");
            provinceInput.classList.add("is-location-verified");

            if (provinceCodeHidden) provinceCodeHidden.value = prov.code;
            if (provinceNameHidden) provinceNameHidden.value = prov.name;

            // Đổi tỉnh thì tự xoá xã/phường đã chọn trước đó
            if (isChanged && wardInput) {
              selectedWardObj = null;
              wardInput.value = "";
              wardInput.classList.remove("is-location-verified");
              wardInput.classList.remove("has-location-error");
              if (wardCodeHidden) wardCodeHidden.value = "";
              if (wardNameHidden) wardNameHidden.value = "";
            }

            // Mở khoá ô Xã/Phường và tự động focus
            if (wardInput) {
              wardInput.disabled = false;
              wardInput.placeholder = t(
                "addr_ward_placeholder",
                "Nhập tìm Phường / Xã...",
              );
              wardInput.focus();
            }

            syncFullDeliveryAddress();
          },
        });

        // Blur validation cho Tỉnh / Thành phố
        provinceInput.addEventListener("blur", () => {
          setTimeout(() => {
            const confirmedName = provinceNameHidden
              ? provinceNameHidden.value.trim()
              : "";
            if (provinceInput.value.trim() !== confirmedName) {
              if (confirmedName) {
                provinceInput.value = confirmedName;
                provinceInput.classList.add("is-location-verified");
              } else {
                provinceInput.value = "";
                provinceInput.classList.remove("is-location-verified");
                if (provinceCodeHidden) provinceCodeHidden.value = "";
                if (wardInput) {
                  wardInput.disabled = true;
                  wardInput.placeholder = t(
                    "addr_ward_placeholder_disabled",
                    "Vui lòng chọn Tỉnh trước...",
                  );
                  wardInput.value = "";
                }
              }
            }
          }, 220);
        });
      }

      // --- CẤU HÌNH BƯỚC 2: PHƯỜNG / XÃ ---
      if (wardInput && wardDropdown) {
        setupAutocompleteSearch({
          inputEl: wardInput,
          dropdownEl: wardDropdown,
          placeholderNoResult: t(
            "addr_no_ward_result",
            "Không tìm thấy Phường / Xã phù hợp trong Tỉnh/Thành này",
          ),
          getItemsFn: (searchVal) => {
            if (
              !selectedProvinceObj ||
              !Array.isArray(selectedProvinceObj.wards)
            )
              return [];
            const q = normalizeVietnamese(searchVal);
            const wards = selectedProvinceObj.wards;
            if (!q) return wards;
            return wards.filter((w) => normalizeVietnamese(w.name).includes(q));
          },
          onSelectFn: (ward) => {
            selectedWardObj = ward;
            wardInput.value = ward.name;
            wardInput.classList.remove("has-location-error");
            wardInput.classList.add("is-location-verified");

            if (wardCodeHidden) wardCodeHidden.value = ward.code;
            if (wardNameHidden) wardNameHidden.value = ward.name;

            // Focus tiếp sang ô Số nhà / Đường
            if (streetInput) {
              streetInput.focus();
            }

            syncFullDeliveryAddress();
          },
        });

        // Blur validation cho Phường / XÃ
        wardInput.addEventListener("blur", () => {
          setTimeout(() => {
            const confirmedName = wardNameHidden
              ? wardNameHidden.value.trim()
              : "";
            if (wardInput.value.trim() !== confirmedName) {
              if (confirmedName) {
                wardInput.value = confirmedName;
                wardInput.classList.add("is-location-verified");
              } else {
                wardInput.value = "";
                wardInput.classList.remove("is-location-verified");
                if (wardCodeHidden) wardCodeHidden.value = "";
              }
            }
          }, 220);
        });
      }

      let isSubmittingDirectly = false;

      function showPageLoading(show) {
        const overlay = document.getElementById("checkout-loading-overlay");
        if (!overlay) return;
        if (show) {
          overlay.style.display = "flex";
          void overlay.offsetWidth;
          overlay.classList.add("is-active");
        } else {
          overlay.classList.remove("is-active");
          setTimeout(() => {
            if (!overlay.classList.contains("is-active")) {
              overlay.style.display = "none";
            }
          }, 220);
        }
      }

      // --- BẢO VỆ KHI SUBMIT FORM: KHÔNG CHO SUBMIT NẾU CHƯA ĐỦ ĐỊA CHỈ HỢP LỆ ---
      if (checkoutForm) {
        checkoutForm.addEventListener(
          "submit",
          async (e) => {
            if (isSubmittingDirectly) return true;

            const currentMode = getCurrentAddressMode();

            if (currentMode === "saved") {
              if (fullAddressField) {
                fullAddressField.value = savedAddress;
              }
              if (deliveryAddressAlias) {
                deliveryAddressAlias.value = savedAddress;
              }

              if (!manager.getState().selectedStore || activeBranchSearchPromise) {
                e.preventDefault();
                e.stopPropagation();

                showPageLoading(true);

                try {
                  const ok = await findNearestBranchForAddress(savedAddress);
                  if (ok && manager.getState().selectedStore) {
                    isSubmittingDirectly = true;
                    checkoutForm.submit();
                    return false;
                  } else {
                    showPageLoading(false);
                    setAddressUnverified(
                      t(
                        "addr_err_saved_unreachable",
                        "Không thể xác định chi nhánh phục vụ cho địa chỉ đã lưu. Vui lòng kiểm tra lại!",
                      ),
                    );
                    setAddressMode("new", { autoConfirm: false });
                    parseAddressIntoCascade(savedAddress);
                  }
                } catch (err) {
                  showPageLoading(false);
                  console.error(err);
                }
                return false;
              }
              return true;
            }

            const provCode = provinceCodeHidden
              ? provinceCodeHidden.value.trim()
              : "";
            const wardCode = wardCodeHidden ? wardCodeHidden.value.trim() : "";
            const street = streetInput ? streetInput.value.trim() : "";

            if (!provCode) {
              e.preventDefault();
              e.stopPropagation();
              if (provinceInput) {
                provinceInput.classList.add("has-location-error");
                provinceInput.focus();
              }
              setAddressUnverified(
                t(
                  "addr_err_prov",
                  "Vui lòng chọn Tỉnh / Thành phố từ danh sách gợi ý!",
                ),
              );
              if (addressWarning)
                addressWarning.scrollIntoView({
                  behavior: "smooth",
                  block: "center",
                });
              return false;
            }

            if (!wardCode) {
              e.preventDefault();
              e.stopPropagation();
              if (wardInput) {
                wardInput.classList.add("has-location-error");
                wardInput.focus();
              }
              setAddressUnverified(
                t(
                  "addr_err_ward",
                  "Vui lòng chọn Phường / Xã từ danh sách gợi ý!",
                ),
              );
              if (addressWarning)
                addressWarning.scrollIntoView({
                  behavior: "smooth",
                  block: "center",
                });
              return false;
            }

            if (!street) {
              e.preventDefault();
              e.stopPropagation();
              if (streetInput) {
                streetInput.classList.add("has-location-error");
                streetInput.focus();
              }
              setAddressUnverified(
                t("addr_err_street", "Vui lòng nhập số nhà, tên đường cụ thể!"),
              );
              if (addressWarning)
                addressWarning.scrollIntoView({
                  behavior: "smooth",
                  block: "center",
                });
              return false;
            }

            syncFullDeliveryAddress();

            // Nếu chưa xác định chi nhánh phục vụ:
            if (!manager.getState().selectedStore || activeBranchSearchPromise) {
              e.preventDefault();
              e.stopPropagation();

              showPageLoading(true);

              try {
                const ok = await findNearestBranchForAddress(
                  fullAddressField.value,
                );
                if (ok && manager.getState().selectedStore) {
                  isSubmittingDirectly = true;
                  checkoutForm.submit();
                  return false;
                }
              } catch (err) {
                console.error(err);
              } finally {
                if (!isSubmittingDirectly) {
                  showPageLoading(false);
                }
              }
              return false;
            }
          },
          true,
        );
      }
    }

    // Modal Opening & Closing
    if (btnChangeStore && modalBackdrop) {
      btnChangeStore.addEventListener("click", (e) => {
        e.preventDefault();
        modalBackdrop.classList.add("is-open");
      });
    }

    if (modalClose && modalBackdrop) {
      modalClose.addEventListener("click", () => {
        modalBackdrop.classList.remove("is-open");
      });
      modalBackdrop.addEventListener("click", (e) => {
        if (e.target === modalBackdrop) {
          modalBackdrop.classList.remove("is-open");
        }
      });
    }

    // Modal store search filter
    if (storeSearchInput && storeListContainer) {
      storeSearchInput.addEventListener("input", (e) => {
        const term = e.target.value.toLowerCase().trim();
        const items = storeListContainer.querySelectorAll(".store-card-item");
        items.forEach((item) => {
          const text = item.textContent.toLowerCase();
          item.style.display = text.includes(term) ? "flex" : "none";
        });
      });
    }

    // Render Store Items inside Modal
    function renderStoreModalList(stores, selectedStore) {
      if (!storeListContainer) return;
      if (!stores || stores.length === 0) {
        storeListContainer.innerHTML = `<p class="muted" style="text-align:center;padding:20px;">${t("gps_loading_stores", "Đang tải danh sách chi nhánh...")}</p>`;
        return;
      }

      storeListContainer.innerHTML = stores
        .map((store, index) => {
          const isSelected =
            selectedStore && Number(selectedStore.id) === Number(store.id);
          const isNearest = index === 0 && store.distance;

          return `
          <div class="store-card-item ${isSelected ? "is-selected" : ""}" data-store-id="${store.id}">
            <div class="store-card-info">
              <div class="store-card-title">
                ${store.name}
                ${isNearest ? `<span class="store-badge-nearest">${t("gps_nearest_badge", "Gần bạn nhất")}</span>` : ""}
              </div>
              <div class="store-card-address">${store.address}</div>
              <div class="store-card-metrics">
                ${store.distance ? `<span class="store-metric-km">${t("gps_distance_away", `📍 Cách ${store.distance.text}`, { distance: store.distance.text })}</span>` : `<span class="store-metric-duration">${t("gps_distance_calculating", "Khoảng cách đang tính")}</span>`}
                ${store.duration ? `<span class="store-metric-duration">⏱ ${store.duration.text}</span>` : ""}
              </div>
            </div>
            <input type="radio" name="modal_store_radio" class="store-card-radio" ${isSelected ? "checked" : ""}>
          </div>
        `;
        })
        .join("");

      storeListContainer
        .querySelectorAll(".store-card-item")
        .forEach((item) => {
          item.addEventListener("click", () => {
            const storeId = Number(item.getAttribute("data-store-id"));
            manager.selectStore(storeId);
            if (modalBackdrop) {
              modalBackdrop.classList.remove("is-open");
            }
          });
        });
    }
  }

  // Auto-init on DOMContentLoaded
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initLocationSelector);
  } else {
    initLocationSelector();
  }

  // Expose to window for testing / modular access
  window.GoongMapService = GoongMapService;
  window.LocationOrderManager = LocationOrderManager;
})();
