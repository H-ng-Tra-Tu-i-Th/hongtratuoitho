/**
 * HUIYI Tea - Goong Maps Location & Nearest Store Selector
 * Implements:
 * 1. GoongMapService (AutoComplete, Place Detail, Forward Geocode, Distance Matrix)
 * 2. LocationOrderManager (GPS/Manual Coordinates resolution, Nearest Store calculation, Manual selection)
 * 3. UI Controller (Banner, Modal, Debounced Autocomplete, GPS trigger)
 */

(function () {
  'use strict';

  function t(key, fallback, vars = {}) {
    const dict = (window.__HUIYI_I18N__ && window.__HUIYI_I18N__.sepay) ? window.__HUIYI_I18N__.sepay : {};
    let text = dict[key] || fallback;
    for (const [k, v] of Object.entries(vars)) {
      text = text.replace(new RegExp(`\\{${k}\\}`, 'g'), v);
    }
    return text;
  }

  class GoongMapService {
    constructor(config) {
      this.apiKey = (config.apiKey || '').trim();
      this.baseUrl = (config.baseUrl || 'https://rsapi.goong.io').replace(/\/+$/, '');
      this.timeoutMs = config.timeoutMs || 8000;
    }

    async fetchWithTimeout(url, signal) {
      if (!this.apiKey) {
        throw new Error('Chưa cấu hình Goong API Key.');
      }
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), this.timeoutMs);
      if (signal) {
        signal.addEventListener('abort', () => controller.abort(), { once: true });
      }

      try {
        const res = await fetch(url, { signal: controller.signal });
        if (!res.ok) {
          throw new Error(`Goong API HTTP ${res.status}: ${res.statusText}`);
        }
        return await res.json();
      } catch (err) {
        if (err.name === 'AbortError') {
          throw new Error(`Yêu cầu Goong API quá thời gian (${this.timeoutMs}ms).`);
        }
        throw err;
      } finally {
        clearTimeout(timer);
      }
    }

    // 1. AutoComplete
    async placeAutoComplete(input, signal) {
      const q = (input || '').trim();
      if (!q) return [];
      const url = `${this.baseUrl}/Place/AutoComplete?input=${encodeURIComponent(q)}&api_key=${encodeURIComponent(this.apiKey)}`;
      const data = await this.fetchWithTimeout(url, signal);
      return data.predictions || [];
    }

    // 2. Place Detail
    async placeDetail(placeId, signal) {
      const url = `${this.baseUrl}/Place/Detail?place_id=${encodeURIComponent(placeId)}&api_key=${encodeURIComponent(this.apiKey)}`;
      const data = await this.fetchWithTimeout(url, signal);
      if (data.status !== 'OK' || !data.result?.geometry?.location) {
        throw new Error('Không thể lấy tọa độ chi tiết từ Goong Place Detail.');
      }
      return {
        coordinates: data.result.geometry.location,
        formattedAddress: data.result.formatted_address || '',
      };
    }

    // 3. Forward Geocoding
    async forwardGeocode(address, signal) {
      const q = (address || '').trim();
      if (!q) throw new Error('Địa chỉ không được để trống.');
      const url = `${this.baseUrl}/geocode?address=${encodeURIComponent(q)}&api_key=${encodeURIComponent(this.apiKey)}`;
      const data = await this.fetchWithTimeout(url, signal);
      const first = data.results?.[0];
      if (!first || !first.geometry?.location) {
        throw new Error('Không tìm thấy tọa độ cho địa chỉ này. Vui lòng kiểm tra lại.');
      }
      return {
        coordinates: first.geometry.location,
        formattedAddress: first.formatted_address || q,
      };
    }

    // Reverse Geocoding: chuyển tọa độ lat, lng thành địa chỉ chi tiết
    async reverseGeocode(lat, lng, signal) {
      const latlngStr = `${lat},${lng}`;
      const url = `${this.baseUrl}/Geocode?latlng=${encodeURIComponent(latlngStr)}&api_key=${encodeURIComponent(this.apiKey)}`;
      const data = await this.fetchWithTimeout(url, signal);
      const first = data.results?.[0];
      if (!first || !first.formatted_address) {
        throw new Error('Không tìm thấy địa chỉ cho tọa độ này.');
      }
      return {
        coordinates: first.geometry?.location || { lat, lng },
        formattedAddress: first.formatted_address,
      };
    }

    // 4. Distance Matrix
    async distanceMatrix(origin, destinations, vehicle = 'bike', signal) {
      if (!destinations || destinations.length === 0) return [];
      const originStr = `${origin.lat},${origin.lng}`;
      const destsStr = destinations.map((d) => `${d.lat},${d.lng}`).join('|');
      const url = `${this.baseUrl}/DistanceMatrix?origins=${encodeURIComponent(originStr)}&destinations=${encodeURIComponent(destsStr)}&vehicle=${encodeURIComponent(vehicle)}&api_key=${encodeURIComponent(this.apiKey)}`;
      const data = await this.fetchWithTimeout(url, signal);
      return data.rows?.[0]?.elements || [];
    }
  }

  class LocationOrderManager {
    constructor(goongService, initialStores = []) {
      this.goongService = goongService;
      this.state = {
        userLocation: null,
        userAddress: '',
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
        try { fn(this.state); } catch (e) { console.error(e); }
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
        let address = '';

        if (params.method === 'gps') {
          coords = await this.getGPSLocation();
          address = `Tọa độ GPS (${coords.lat.toFixed(5)}, ${coords.lng.toFixed(5)})`;
          if (this.goongService && this.goongService.apiKey) {
            try {
              const rev = await this.goongService.reverseGeocode(coords.lat, coords.lng);
              if (rev && rev.formattedAddress) {
                address = rev.formattedAddress;
              }
            } catch (revErr) {
              console.warn('[Location] Reverse geocoding fallback:', revErr.message);
            }
          }
        } else if (params.method === 'place_id') {
          const detail = await this.goongService.placeDetail(params.placeId);
          coords = detail.coordinates;
          address = params.description || detail.formattedAddress;
        } else if (params.method === 'manual_address') {
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
      const stores = storesList || this.state.storesWithDistance;
      if (!stores || stores.length === 0) return { nearest: null, sortedStores: [] };

      this.updateState({ isLoading: true, error: null });
      try {
        const validStores = [];
        const invalidStores = [];

        stores.forEach((s) => {
          if (typeof s.lat === 'number' && typeof s.lng === 'number' && !isNaN(s.lat) && !isNaN(s.lng)) {
            validStores.push(s);
          } else {
            invalidStores.push(Object.assign({}, s, { matrixStatus: 'NO_COORDINATES' }));
          }
        });

        let evaluatedStores = [];
        if (validStores.length > 0) {
          const destinations = validStores.map((s) => ({ lat: s.lat, lng: s.lng }));
          const elements = await this.goongService.distanceMatrix(userCoords, destinations, 'bike');

          validStores.forEach((store, i) => {
            const el = elements[i];
            if (el && el.status === 'OK' && el.distance) {
              evaluatedStores.push(Object.assign({}, store, {
                distance: el.distance,
                duration: el.duration,
                matrixStatus: 'OK',
              }));
            } else {
              evaluatedStores.push(Object.assign({}, store, {
                matrixStatus: el?.status || 'ERROR',
              }));
            }
          });
        }

        const okStores = evaluatedStores.filter((s) => s.matrixStatus === 'OK' && s.distance);
        okStores.sort((a, b) => (a.distance.value || 0) - (b.distance.value || 0));

        const remaining = [
          ...evaluatedStores.filter((s) => s.matrixStatus !== 'OK'),
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
      const found = this.state.storesWithDistance.find((s) => Number(s.id) === Number(storeId));
      if (found) {
        this.updateState({ selectedStore: found });
      }
      return found;
    }

    getGPSLocation() {
      return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
          return reject(new Error('Trình duyệt không hỗ trợ định vị GPS.'));
        }
        navigator.geolocation.getCurrentPosition(
          (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
          (err) => {
            let msg = 'Không thể lấy vị trí hiện tại.';
            if (err.code === 1) msg = 'Bạn đã từ chối quyền truy cập vị trí. Vui lòng nhập địa chỉ bằng tay.';
            else if (err.code === 2) msg = 'Vị trí hiện tại không khả dụng.';
            else if (err.code === 3) msg = 'Định vị GPS hết thời gian chờ.';
            reject(new Error(msg));
          },
          { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
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
    const wrap = document.querySelector('[data-location-container]');
    if (!wrap) return;

    const apiKey = wrap.getAttribute('data-goong-key') || '';
    const goong = new GoongMapService({ apiKey });
    const manager = new LocationOrderManager(goong);

    const bannerEl = wrap.querySelector('[data-location-banner]');
    const bannerStoreTitle = wrap.querySelector('[data-banner-store-name]');
    const bannerDistanceWrap = wrap.querySelector('[data-banner-distance-wrap]');
    const bannerDistanceText = wrap.querySelector('[data-banner-distance-text]');
    const bannerDurationText = wrap.querySelector('[data-banner-duration-text]');
    const bannerShippingText = wrap.querySelector('[data-banner-shipping-text]');
    const reviewShippingWrap = document.querySelector('[data-review-shipping-wrap]');
    const reviewShippingText = document.querySelector('[data-review-shipping-text]');
    const bannerReasonWrap = wrap.querySelector('[data-banner-reason-wrap]');
    const bannerReasonText = wrap.querySelector('[data-banner-reason-text]');
    const btnChangeStore = wrap.querySelector('[data-btn-change-store]');
    const hiddenStoreInput = document.querySelector('[name="selected_store_id"]');

    const addressInput = document.querySelector('[name="address"]');
    const btnGps = wrap.querySelector('[data-btn-gps]');
    const autocompleteDropdown = wrap.querySelector('[data-autocomplete-dropdown]');
    const addressWarning = wrap.querySelector('[data-address-warning]');
    const verifiedInput = wrap.querySelector('[data-address-verified-input]');
    const checkoutForm = document.querySelector('[data-checkout-form]');

    let isAddressVerified = false;
    let verifiedAddressText = '';

    function setAddressVerified(text) {
      isAddressVerified = true;
      verifiedAddressText = text;
      if (verifiedInput) verifiedInput.value = '1';
      if (addressWarning) {
        addressWarning.classList.remove('is-visible');
        addressWarning.innerHTML = '';
      }
      if (addressInput) {
        addressInput.classList.remove('has-location-error');
        addressInput.classList.add('is-location-verified');
      }
    }

    function setAddressUnverified(customMessage) {
      isAddressVerified = false;
      if (verifiedInput) verifiedInput.value = '0';
      if (addressInput) {
        addressInput.classList.remove('is-location-verified');
      }
      if (customMessage && addressWarning) {
        addressWarning.innerHTML = '⚠️ ' + customMessage;
        addressWarning.classList.add('is-visible');
      }
    }

    const modalBackdrop = document.querySelector('[data-store-modal]');
    const modalClose = modalBackdrop ? modalBackdrop.querySelector('[data-modal-close]') : null;
    const storeListContainer = modalBackdrop ? modalBackdrop.querySelector('[data-store-list]') : null;
    const storeSearchInput = modalBackdrop ? modalBackdrop.querySelector('[data-store-search]') : null;

    // Load stores from API
    fetch(wrap.getAttribute('data-stores-endpoint') || '/api/stores.php')
      .then((r) => r.json())
      .then((res) => {
        if (res.success && Array.isArray(res.data)) {
          manager.setStores(res.data);
          // If customer already has an address pre-filled, try computing distance
          if (addressInput && addressInput.value.trim().length > 5 && apiKey) {
            handleAddressLookup(addressInput.value.trim()).then((ok) => {
              if (ok) setAddressVerified(addressInput.value.trim());
            });
          }
        }
      })
      .catch((e) => console.warn('[Location] Failed to fetch stores:', e));

    // Subscribe to state updates to render banner & modal
    manager.subscribe((state) => {
      if (!bannerEl) return;
      const bannerIcon = bannerEl.querySelector('.location-banner-icon');

      if (state.isLoading) {
        bannerEl.classList.add('is-loading');
        bannerEl.classList.remove('is-updated');
        if (bannerIcon) bannerIcon.classList.add('is-spinning');
        if (bannerReasonWrap) bannerReasonWrap.style.display = 'none';
        if (bannerShippingText) bannerShippingText.style.display = 'none';
        if (reviewShippingWrap) reviewShippingWrap.style.display = 'none';

        bannerStoreTitle.textContent = t('gps_calculating_nearest', 'Đang tính toán khoảng cách và tìm chi nhánh gần nhất...');
        bannerDistanceWrap.style.display = 'inline-flex';
        bannerDistanceText.textContent = t('gps_calculating_distance', 'Đang tính km...');
        bannerDurationText.textContent = t('gps_scanning_route', '⏱ Đang quét đường xe chạy');
        if (btnChangeStore) {
          btnChangeStore.disabled = true;
          btnChangeStore.style.opacity = '0.6';
          btnChangeStore.textContent = t('gps_calculating', 'Đang tính...');
        }
      } else if (state.selectedStore) {
        bannerEl.classList.remove('is-loading');
        if (bannerIcon) bannerIcon.classList.remove('is-spinning');
        if (btnChangeStore) {
          btnChangeStore.disabled = false;
          btnChangeStore.style.opacity = '1';
          btnChangeStore.textContent = t('gps_change_store', 'Đổi chi nhánh');
        }

        // Kích hoạt hiệu ứng reload lại component
        bannerEl.classList.remove('is-updated');
        void bannerEl.offsetWidth; // Force reflow để chạy lại animation
        bannerEl.classList.add('is-updated');

        bannerStoreTitle.textContent = state.selectedStore.name + (state.selectedStore.address ? ' - ' + state.selectedStore.address : '');
        if (hiddenStoreInput) {
          hiddenStoreInput.value = state.selectedStore.id;
        }

        if (state.selectedStore.distance) {
          bannerDistanceWrap.style.display = 'inline-flex';
          bannerDistanceText.textContent = state.selectedStore.distance.text;
          bannerDurationText.textContent = state.selectedStore.duration ? t('gps_travel_time', `(~${state.selectedStore.duration.text} di chuyển)`, { time: state.selectedStore.duration.text }) : '';

          // Tính số km từ Goong Maps API (distance.value là mét -> chia 1000 để ra km)
          const distanceKm = (state.selectedStore.distance.value || 0) / 1000;
          const isFreeship = distanceKm <= 3.0;

          // In test console log để dễ theo dõi
          console.log(`[Goong Map] Khoảng cách: ${distanceKm.toFixed(2)} km -> ${isFreeship ? 'Miễn phí giao hàng (Freeship)' : 'Tính phí theo Grab'}`);

          // Cập nhật nhãn phí ship trên Banner chi nhánh
          if (bannerShippingText) {
            bannerShippingText.style.display = 'inline-flex';
            bannerShippingText.className = 'location-banner-shipping ' + (isFreeship ? 'is-free' : 'is-grab');
            bannerShippingText.innerHTML = isFreeship
              ? `🎉 ${t('shipping_fee_freeship', 'Miễn phí giao hàng (Freeship ≤ 3km)')}`
              : `🛵 ${t('shipping_fee_grab', 'Tính phí theo giá Grab (> 3km)')}`;
          }

          // Cập nhật dòng phí giao hàng trong Bảng tổng tiền đơn hàng (Order Review)
          if (reviewShippingWrap && reviewShippingText) {
            reviewShippingWrap.style.display = 'flex';
            reviewShippingText.className = isFreeship ? 'shipping-text-free' : 'shipping-text-grab';
            reviewShippingText.textContent = isFreeship
              ? t('shipping_fee_freeship', 'Miễn phí giao hàng (Freeship ≤ 3km)')
              : t('shipping_fee_grab', 'Tính phí theo giá Grab (> 3km)');
          }
        } else {
          bannerDistanceWrap.style.display = 'none';
          if (bannerShippingText) bannerShippingText.style.display = 'none';
          if (reviewShippingWrap) reviewShippingWrap.style.display = 'none';
        }

        // Hiển thị lý do vì sao chọn chi nhánh này
        const isNearest = state.nearestStore && Number(state.nearestStore.id) === Number(state.selectedStore.id);
        if (bannerReasonWrap && bannerReasonText) {
          bannerReasonWrap.style.display = 'block';
          if (isNearest && state.selectedStore.distance) {
            const reasonTitle = t('gps_reason_selected', 'Lý do chọn:');
            const reasonDesc = t('gps_reason_nearest_desc', 'Chi nhánh có khoảng cách gần bạn nhất (chỉ <strong>{distance}</strong>), giúp giao nhanh & giữ lạnh đồ uống tốt nhất.', { distance: state.selectedStore.distance.text });
            bannerReasonText.innerHTML = `📍 <strong>${reasonTitle}</strong> ${reasonDesc}`;
          } else if (!isNearest) {
            const manualTitle = t('gps_manual_choice', 'Đổi theo lựa chọn của bạn:');
            const manualDesc = t('gps_reason_manual_desc', 'Chi nhánh cách vị trí nhận <strong>{distance}</strong>.', { distance: state.selectedStore.distance ? state.selectedStore.distance.text : 'N/A' });
            bannerReasonText.innerHTML = `👉 <strong>${manualTitle}</strong> ${manualDesc}`;
          } else {
            bannerReasonWrap.style.display = 'none';
          }
        }
      } else {
        bannerEl.classList.remove('is-loading');
        if (bannerIcon) bannerIcon.classList.remove('is-spinning');
        if (bannerReasonWrap) bannerReasonWrap.style.display = 'none';
        if (bannerShippingText) bannerShippingText.style.display = 'none';
        if (reviewShippingWrap) reviewShippingWrap.style.display = 'none';
        if (btnChangeStore) {
          btnChangeStore.disabled = false;
          btnChangeStore.style.opacity = '1';
          btnChangeStore.textContent = t('gps_change_store', 'Đổi chi nhánh');
        }
        bannerStoreTitle.textContent = t('gps_auto_detecting', 'Đang tự động chọn chi nhánh gần nhất...');
        bannerDistanceWrap.style.display = 'none';
      }

      renderStoreModalList(state.storesWithDistance, state.selectedStore);
    });

    // GPS Click Handler
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

    // AutoComplete Debounced Search
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
          // Reload lại component sang trạng thái đang chờ địa chỉ mới
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

      // Cảnh báo khi người dùng rời ô nhập mà không bấm chọn gợi ý
      addressInput.addEventListener('blur', () => {
        setTimeout(() => {
          if (!isAddressVerified && addressInput.value.trim().length > 0) {
            addressInput.classList.add('has-location-error');
            setAddressUnverified(t('gps_select_hint', 'Vui lòng bấm chọn một địa chỉ cụ thể từ danh sách gợi ý hoặc bấm nút "Dùng vị trí hiện tại" để xác định chi nhánh.'));
          }
        }, 220);
      });

      // Close dropdown when clicking outside
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

            // Kích hoạt ngay lập tức trạng thái tải lại của banner
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

    // Geocoding helper for initial address
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

    // Chặn gửi form nếu chưa chọn địa chỉ từ popup hoặc GPS
    if (checkoutForm && apiKey) {
      checkoutForm.addEventListener('submit', (e) => {
        if (!isAddressVerified) {
          e.preventDefault();
          e.stopPropagation();

          if (addressInput) {
            addressInput.classList.add('has-location-error');
            addressInput.focus();

            // Mở lại danh sách gợi ý nếu đã gõ chữ
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

    // Modal Opening & Closing
    if (btnChangeStore && modalBackdrop) {
      btnChangeStore.addEventListener('click', (e) => {
        e.preventDefault();
        modalBackdrop.classList.add('is-open');
      });
    }

    if (modalClose && modalBackdrop) {
      modalClose.addEventListener('click', () => {
        modalBackdrop.classList.remove('is-open');
      });
      modalBackdrop.addEventListener('click', (e) => {
        if (e.target === modalBackdrop) {
          modalBackdrop.classList.remove('is-open');
        }
      });
    }

    // Modal store search filter
    if (storeSearchInput && storeListContainer) {
      storeSearchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase().trim();
        const items = storeListContainer.querySelectorAll('.store-card-item');
        items.forEach((item) => {
          const text = item.textContent.toLowerCase();
          item.style.display = text.includes(term) ? 'flex' : 'none';
        });
      });
    }

    // Render Store Items inside Modal
    function renderStoreModalList(stores, selectedStore) {
      if (!storeListContainer) return;
      if (!stores || stores.length === 0) {
        storeListContainer.innerHTML = `<p class="muted" style="text-align:center;padding:20px;">${t('gps_loading_stores', 'Đang tải danh sách chi nhánh...')}</p>`;
        return;
      }

      storeListContainer.innerHTML = stores
        .map((store, index) => {
          const isSelected = selectedStore && Number(selectedStore.id) === Number(store.id);
          const isNearest = index === 0 && store.distance;

          return `
          <div class="store-card-item ${isSelected ? 'is-selected' : ''}" data-store-id="${store.id}">
            <div class="store-card-info">
              <div class="store-card-title">
                ${store.name}
                ${isNearest ? `<span class="store-badge-nearest">${t('gps_nearest_badge', 'Gần bạn nhất')}</span>` : ''}
              </div>
              <div class="store-card-address">${store.address}</div>
              <div class="store-card-metrics">
                ${store.distance ? `<span class="store-metric-km">${t('gps_distance_away', `📍 Cách ${store.distance.text}`, { distance: store.distance.text })}</span>` : `<span class="store-metric-duration">${t('gps_distance_calculating', 'Khoảng cách đang tính')}</span>`}
                ${store.duration ? `<span class="store-metric-duration">⏱ ${store.duration.text}</span>` : ''}
              </div>
            </div>
            <input type="radio" name="modal_store_radio" class="store-card-radio" ${isSelected ? 'checked' : ''}>
          </div>
        `;
        })
        .join('');

      storeListContainer.querySelectorAll('.store-card-item').forEach((item) => {
        item.addEventListener('click', () => {
          const storeId = Number(item.getAttribute('data-store-id'));
          manager.selectStore(storeId);
          if (modalBackdrop) {
            modalBackdrop.classList.remove('is-open');
          }
        });
      });
    }
  }

  // Auto-init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLocationSelector);
  } else {
    initLocationSelector();
  }

  // Expose to window for testing / modular access
  window.GoongMapService = GoongMapService;
  window.LocationOrderManager = LocationOrderManager;
})();
