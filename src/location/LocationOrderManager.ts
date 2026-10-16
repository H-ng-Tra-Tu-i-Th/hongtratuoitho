import { GoongMapService } from './GoongMapService';
import {
  Coordinates,
  GeolocationError,
  LocationOrderState,
  Store,
  StoreWithDistance,
  VehicleType,
} from './types';

export type StateListener = (state: LocationOrderState) => void;

export interface ResolveParamsGPS {
  method: 'gps';
  enableHighAccuracy?: boolean;
  timeoutMs?: number;
}

export interface ResolveParamsPlaceId {
  method: 'place_id';
  placeId: string;
  description?: string;
}

export interface ResolveParamsAddress {
  method: 'manual_address';
  address: string;
}

export type ResolveLocationParams =
  | ResolveParamsGPS
  | ResolveParamsPlaceId
  | ResolveParamsAddress;

/**
 * Controller/Manager orchestrating user location determination (GPS vs Goong Maps),
 * distance matrix computation across stores, nearest store recommendation,
 * and store manual override.
 */
export class LocationOrderManager {
  private readonly goongService: GoongMapService;
  private state: LocationOrderState;
  private listeners: Set<StateListener> = new Set();
  private vehicle: VehicleType = 'bike';

  constructor(goongService: GoongMapService, initialStores: Store[] = []) {
    this.goongService = goongService;
    this.state = {
      userLocation: null,
      userAddress: '',
      locationSource: null,
      storesWithDistance: initialStores.map((s) => ({ ...s })),
      selectedStore: null,
      nearestStore: null,
      isLoading: false,
      isMatrixCalculating: false,
      error: null,
    };
  }

  /**
   * Returns current snapshot of location and order state.
   */
  public getState(): Readonly<LocationOrderState> {
    return this.state;
  }

  /**
   * Subscribe to state updates. Returns an unsubscribe function.
   */
  public subscribe(listener: StateListener): () => void {
    this.listeners.add(listener);
    // Immediately emit current state to new subscriber
    listener(this.state);
    return () => {
      this.listeners.delete(listener);
    };
  }

  private updateState(partial: Partial<LocationOrderState>): void {
    this.state = { ...this.state, ...partial };
    for (const listener of this.listeners) {
      try {
        listener(this.state);
      } catch (err) {
        console.error('[LocationOrderManager] Listener threw error:', err);
      }
    }
  }

  public setVehicle(vehicle: VehicleType): void {
    this.vehicle = vehicle;
  }

  /**
   * Set or update store list in state.
   */
  public setStores(stores: Store[]): void {
    const formatted: StoreWithDistance[] = stores.map((s) => ({ ...s }));
    this.updateState({ storesWithDistance: formatted });
  }

  /**
   * Giai đoạn 1: Thu thập vị trí người dùng (User Location)
   * Kịch bản A: GPS thiết bị (navigator.geolocation)
   * Kịch bản B: Place Detail (từ AutoComplete place_id) hoặc Forward Geocoding (fallback)
   */
  public async resolveUserCoordinates(params: ResolveLocationParams): Promise<Coordinates> {
    this.updateState({ isLoading: true, error: null });

    try {
      let coords: Coordinates;
      let address = '';
      let source: LocationOrderState['locationSource'] = null;

      if (params.method === 'gps') {
        coords = await this.getCurrentGPSLocation(
          params.enableHighAccuracy ?? true,
          params.timeoutMs ?? 10000
        );
        source = 'gps';
        address = `Vị trí GPS (${coords.lat.toFixed(5)}, ${coords.lng.toFixed(5)})`;
        try {
          const rev = await this.goongService.reverseGeocode(coords);
          if (rev?.formattedAddress) {
            address = rev.formattedAddress;
          }
        } catch (revError: any) {
          console.warn('[LocationOrderManager] Reverse geocode fallback:', revError?.message);
        }
      } else if (params.method === 'place_id') {
        const detail = await this.goongService.placeDetail(params.placeId);
        coords = detail.coordinates;
        address = params.description || detail.formattedAddress;
        source = 'autocomplete';
      } else if (params.method === 'manual_address') {
        const geocode = await this.goongService.forwardGeocode(params.address);
        coords = geocode.coordinates;
        address = geocode.formattedAddress || params.address;
        source = 'manual_geocode';
      } else {
        throw new Error('Unsupported location resolve method.');
      }

      this.updateState({
        userLocation: coords,
        userAddress: address,
        locationSource: source,
        isLoading: false,
      });

      return coords;
    } catch (error: any) {
      const errorMessage = error?.message || 'Không thể xác định vị trí. Vui lòng thử lại.';
      this.updateState({
        isLoading: false,
        error: errorMessage,
      });
      throw error;
    }
  }

  /**
   * Giai đoạn 2: Tính khoảng cách & Chọn chi nhánh tối ưu
   * Lấy origins = userCoords, destinations = chuỗi tọa độ các chi nhánh.
   * Gọi Goong Distance Matrix API, sắp xếp tăng dần distance.value (meters).
   * Mặc định gán chi nhánh có số km nhỏ nhất làm selectedStore.
   */
  public async calculateNearestStore(
    userCoords: Coordinates,
    storesList?: Store[]
  ): Promise<{ nearest: StoreWithDistance | null; sortedStores: StoreWithDistance[] }> {
    const stores = storesList || this.state.storesWithDistance;
    if (!stores || stores.length === 0) {
      this.updateState({
        storesWithDistance: [],
        selectedStore: null,
        nearestStore: null,
        error: 'Danh sách chi nhánh hiện đang trống.',
      });
      return { nearest: null, sortedStores: [] };
    }

    this.updateState({ isMatrixCalculating: true, error: null });

    try {
      // Tách các chi nhánh có tọa độ hợp lệ
      const validStores: Store[] = [];
      const invalidStores: StoreWithDistance[] = [];

      for (const s of stores) {
        if (
          s.is_active !== false &&
          typeof s.lat === 'number' &&
          typeof s.lng === 'number' &&
          !isNaN(s.lat) &&
          !isNaN(s.lng)
        ) {
          validStores.push(s);
        } else {
          invalidStores.push({
            ...s,
            matrixStatus: 'NO_COORDINATES',
          });
        }
      }

      let evaluatedStores: StoreWithDistance[] = [];

      if (validStores.length > 0) {
        const destinations = validStores.map((s) => ({
          lat: s.lat as number,
          lng: s.lng as number,
        }));

        const elements = await this.goongService.distanceMatrix(
          userCoords,
          destinations,
          this.vehicle
        );

        validStores.forEach((store, index) => {
          const el = elements[index];
          if (el && el.status === 'OK' && el.distance) {
            evaluatedStores.push({
              ...store,
              distance: el.distance,
              duration: el.duration,
              matrixStatus: 'OK',
            });
          } else {
            evaluatedStores.push({
              ...store,
              matrixStatus: (el?.status as any) || 'ERROR',
            });
          }
        });
      }

      // Sắp xếp tăng dần theo distance.value (meters)
      const okStores = evaluatedStores.filter(
        (s) => s.matrixStatus === 'OK' && s.distance && typeof s.distance.value === 'number'
      );
      okStores.sort((a, b) => (a.distance!.value || 0) - (b.distance!.value || 0));

      const otherStores = [
        ...evaluatedStores.filter((s) => s.matrixStatus !== 'OK'),
        ...invalidStores,
      ];

      const sortedStores = [...okStores, ...otherStores];
      const nearest = okStores.length > 0 ? okStores[0] : null;

      // Mặc định gán chi nhánh có số km nhỏ nhất
      this.updateState({
        storesWithDistance: sortedStores,
        nearestStore: nearest,
        selectedStore: nearest,
        isMatrixCalculating: false,
      });

      return { nearest, sortedStores };
    } catch (error: any) {
      const errorMessage =
        error?.message || 'Không thể tính toán khoảng cách chi nhánh từ Goong Maps.';
      this.updateState({
        isMatrixCalculating: false,
        error: errorMessage,
      });
      throw error;
    }
  }

  /**
   * Giai đoạn 3: UX/UI & Quyền thay đổi chi nhánh (Manual Override)
   * Cho phép khách hàng chủ động chọn chi nhánh khác.
   */
  public selectStore(storeId: number): StoreWithDistance | null {
    const found = this.state.storesWithDistance.find((s) => s.id === storeId);
    if (!found) {
      console.warn(`[LocationOrderManager] Store id ${storeId} not found in state.`);
      return null;
    }

    this.updateState({ selectedStore: found });
    return found;
  }

  /**
   * Helper debounce cho ô tìm kiếm gợi ý AutoComplete
   */
  public static createDebounce<T extends (...args: any[]) => any>(
    func: T,
    waitMs: number = 400
  ): (...args: Parameters<T>) => void {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;
    return (...args: Parameters<T>) => {
      if (timeoutId !== null) {
        clearTimeout(timeoutId);
      }
      timeoutId = setTimeout(() => {
        func(...args);
      }, waitMs);
    };
  }

  /**
   * Helper bọc navigator.geolocation thành Promise với timeout và thông báo tiếng Việt.
   */
  private getCurrentGPSLocation(
    enableHighAccuracy: boolean,
    timeoutMs: number
  ): Promise<Coordinates> {
    return new Promise((resolve, reject) => {
      if (typeof window === 'undefined' || !navigator.geolocation) {
        return reject(
          new GeolocationError('Thiết bị hoặc trình duyệt không hỗ trợ dịch vụ định vị GPS.')
        );
      }

      navigator.geolocation.getCurrentPosition(
        (position) => {
          resolve({
            lat: position.coords.latitude,
            lng: position.coords.longitude,
          });
        },
        (error) => {
          let message = 'Không thể lấy vị trí hiện tại.';
          switch (error.code) {
            case error.PERMISSION_DENIED:
              message =
                'Bạn đã từ chối quyền truy cập vị trí. Vui lòng nhập địa chỉ nhận hàng bằng tay.';
              break;
            case error.POSITION_UNAVAILABLE:
              message = 'Tín hiệu GPS không khả dụng hoặc bị gián đoạn.';
              break;
            case error.TIMEOUT:
              message = 'Yêu cầu định vị GPS đã hết thời gian chờ.';
              break;
          }
          reject(new GeolocationError(message, error.code));
        },
        {
          enableHighAccuracy,
          timeout: timeoutMs,
          maximumAge: 30000,
        }
      );
    });
  }
}
