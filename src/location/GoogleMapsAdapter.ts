import { IMapService } from './IMapService';
import {
  Coordinates,
  DistanceMatrixElement,
  DistanceMatrixResult,
  ForwardGeocodeResult,
  GoogleDistanceMatrixResponse,
  GoogleGeocodeResponse,
  GoogleMapsApiError,
  GoogleMapsConfig,
  GoogleTravelMode,
  ReverseGeocodeResult,
  VehicleType,
} from './types';

/**
 * Adapter integrating Google Maps Platform API into the application.
 * Conforms to IMapService interface, supporting:
 * 1. FORWARD_GEOCODE: address string -> { lat, lng, formattedAddress }
 * 2. REVERSE_GEOCODE: { lat, lng } -> { formattedAddress, lat, lng }
 * 3. DISTANCE_MATRIX: (origin, destination) -> { distanceKm, distanceMeters, durationMinutes, durationSeconds }
 */
export class GoogleMapsAdapter implements IMapService {
  public readonly providerName = 'GoogleMaps';

  private readonly apiKey: string;
  private readonly baseUrl: string;
  private readonly timeoutMs: number;
  private readonly language: string;
  private readonly region: string;
  private readonly defaultMode: GoogleTravelMode;

  constructor(config: GoogleMapsConfig) {
    if (!config.apiKey || typeof config.apiKey !== 'string' || !config.apiKey.trim()) {
      throw new GoogleMapsApiError('Google Maps API key is required and cannot be empty.');
    }
    this.apiKey = config.apiKey.trim();
    this.baseUrl = (config.baseUrl || 'https://maps.googleapis.com/maps/api').replace(/\/+$/, '');
    this.timeoutMs = config.timeoutMs && config.timeoutMs > 0 ? config.timeoutMs : 8000;
    this.language = (config.language || 'vi').trim();
    this.region = (config.region || 'vn').trim();
    this.defaultMode = config.defaultMode || 'driving';
  }

  /**
   * Google Maps requires strict format: `${lat},${lng}`
   */
  private formatLatLng(coords: Coordinates): string {
    if (
      typeof coords.lat !== 'number' ||
      typeof coords.lng !== 'number' ||
      isNaN(coords.lat) ||
      isNaN(coords.lng) ||
      coords.lat < -90.0 ||
      coords.lat > 90.0 ||
      coords.lng < -180.0 ||
      coords.lng > 180.0
    ) {
      throw new GoogleMapsApiError(
        `Tọa độ không hợp lệ: (${coords.lat}, ${coords.lng}). Giá trị lat [-90, 90], lng [-180, 180].`
      );
    }
    return `${coords.lat},${coords.lng}`;
  }

  /**
   * Google Maps Distance Matrix REST API supports: 'driving' (default), 'walking', 'bicycling', 'transit'.
   * Note: 'two_wheeler' is only available in Routes API v2, causing INVALID_REQUEST on distancematrix/json.
   * For motorbike/car delivery across roads, 'driving' is the standard, accurate mode.
   */
  private normalizeMode(mode?: GoogleTravelMode | VehicleType): GoogleTravelMode {
    if (!mode) return this.defaultMode;
    const lower = String(mode).toLowerCase();
    if (lower === 'walking' || lower === 'bicycling' || lower === 'transit') {
      return lower as GoogleTravelMode;
    }
    // All road delivery vehicles (bike, two_wheeler, car, taxi, truck, driving) map to 'driving'
    return 'driving';
  }

  /**
   * Performs an HTTP GET request with timeout and AbortSignal support.
   */
  private async fetchWithTimeout<T>(url: string, externalSignal?: AbortSignal): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);

    if (externalSignal) {
      if (externalSignal.aborted) {
        clearTimeout(timer);
        throw new GoogleMapsApiError('Request was aborted prior to execution.');
      }
      externalSignal.addEventListener('abort', () => controller.abort(), { once: true });
    }

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new GoogleMapsApiError(
          `Google Maps API HTTP error: ${response.status} ${response.statusText}`,
          response.status
        );
      }

      const data = (await response.json()) as T;
      return data;
    } catch (error: any) {
      if (error.name === 'AbortError') {
        throw new GoogleMapsApiError(
          `Google Maps API request timed out after ${this.timeoutMs}ms.`
        );
      }
      if (error instanceof GoogleMapsApiError) {
        throw error;
      }
      throw new GoogleMapsApiError(
        error?.message || 'Network request to Google Maps Platform failed.'
      );
    } finally {
      clearTimeout(timer);
    }
  }

  /**
   * 1. FORWARD_GEOCODE
   * Nhận địa chỉ text -> Trả về { lat, lng, formattedAddress }
   * Google Geocoding API: GET https://maps.googleapis.com/maps/api/geocode/json?address={address}&key={KEY}&language=vi&region=vn
   */
  public async forwardGeocode(
    address: string,
    signal?: AbortSignal
  ): Promise<ForwardGeocodeResult> {
    const query = (address || '').trim();
    if (!query) {
      throw new GoogleMapsApiError('Địa chỉ tìm kiếm (address) không được để trống.');
    }

    const endpoint = `${this.baseUrl}/geocode/json?address=${encodeURIComponent(
      query
    )}&key=${encodeURIComponent(this.apiKey)}&language=${encodeURIComponent(
      this.language
    )}&region=${encodeURIComponent(this.region)}`;

    const response = await this.fetchWithTimeout<GoogleGeocodeResponse>(endpoint, signal);

    if (response.status === 'ZERO_RESULTS' || !response.results || response.results.length === 0) {
      throw new GoogleMapsApiError(
        `Không tìm thấy tọa độ cho địa chỉ "${query}". Vui lòng kiểm tra lại.`,
        200,
        'ZERO_RESULTS'
      );
    }

    if (response.status !== 'OK') {
      throw new GoogleMapsApiError(
        `Google Geocoding failed: ${response.error_message || response.status}`,
        undefined,
        response.status
      );
    }

    const first = response.results[0];
    const loc = first.geometry?.location;
    if (!loc || typeof loc.lat !== 'number' || typeof loc.lng !== 'number') {
      throw new GoogleMapsApiError(
        'Phản hồi Geocode của Google không chứa tọa độ geometry.location hợp lệ.'
      );
    }

    return {
      lat: loc.lat,
      lng: loc.lng,
      formattedAddress: first.formatted_address || query,
      coordinates: { lat: loc.lat, lng: loc.lng },
    };
  }

  /**
   * 2. REVERSE_GEOCODE
   * Nhận { lat, lng } -> Trả về { formattedAddress, lat, lng }
   * Google Geocoding API: GET https://maps.googleapis.com/maps/api/geocode/json?latlng={lat},{lng}&key={KEY}&language=vi&region=vn
   */
  public async reverseGeocode(
    coords: Coordinates,
    signal?: AbortSignal
  ): Promise<ReverseGeocodeResult> {
    const latlngStr = this.formatLatLng(coords);
    const endpoint = `${this.baseUrl}/geocode/json?latlng=${encodeURIComponent(
      latlngStr
    )}&key=${encodeURIComponent(this.apiKey)}&language=${encodeURIComponent(
      this.language
    )}&region=${encodeURIComponent(this.region)}`;

    const response = await this.fetchWithTimeout<GoogleGeocodeResponse>(endpoint, signal);

    if (response.status === 'ZERO_RESULTS' || !response.results || response.results.length === 0) {
      throw new GoogleMapsApiError(
        `Không thể tìm thấy địa chỉ cho tọa độ (${latlngStr}).`,
        200,
        'ZERO_RESULTS'
      );
    }

    if (response.status !== 'OK') {
      throw new GoogleMapsApiError(
        `Google Reverse Geocoding failed: ${response.error_message || response.status}`,
        undefined,
        response.status
      );
    }

    const first = response.results[0];
    const loc = first.geometry?.location || coords;

    return {
      formattedAddress: first.formatted_address || `${coords.lat}, ${coords.lng}`,
      lat: loc.lat,
      lng: loc.lng,
      coordinates: { lat: loc.lat, lng: loc.lng },
    };
  }

  /**
   * Place Detail / Place ID Geocoding
   * Google Geocoding API natively supports: GET /geocode/json?place_id={place_id}&key={KEY}
   */
  public async placeDetail(
    placeId: string,
    signal?: AbortSignal
  ): Promise<{ coordinates: Coordinates; formattedAddress: string }> {
    const id = (placeId || '').trim();
    if (!id) {
      throw new GoogleMapsApiError('place_id is required for Place Detail query.');
    }

    const endpoint = `${this.baseUrl}/geocode/json?place_id=${encodeURIComponent(
      id
    )}&key=${encodeURIComponent(this.apiKey)}&language=${encodeURIComponent(
      this.language
    )}&region=${encodeURIComponent(this.region)}`;

    const response = await this.fetchWithTimeout<GoogleGeocodeResponse>(endpoint, signal);

    if (response.status !== 'OK' || !response.results || response.results.length === 0) {
      throw new GoogleMapsApiError(
        `Google Geocoding by place_id "${id}" failed: ${response.error_message || response.status}`,
        undefined,
        response.status
      );
    }

    const first = response.results[0];
    const loc = first.geometry?.location;
    if (!loc || typeof loc.lat !== 'number' || typeof loc.lng !== 'number') {
      throw new GoogleMapsApiError(
        'Google Geocoding by place_id does not contain valid geometry coordinates.'
      );
    }

    return {
      coordinates: { lat: loc.lat, lng: loc.lng },
      formattedAddress: first.formatted_address || '',
    };
  }

  /**
   * 3. DISTANCE_MATRIX
   * Nhận 1 điểm xuất phát (Quán) và 1 điểm đến (Khách)
   * -> Trả về { distanceKm, distanceMeters, durationMinutes, durationSeconds }
   * Hoặc nhận mảng điểm đến -> Trả về DistanceMatrixElement[] phục vụ đa chi nhánh
   */
  public async distanceMatrix(
    origin: Coordinates,
    destination: Coordinates,
    mode?: GoogleTravelMode | VehicleType,
    signal?: AbortSignal
  ): Promise<DistanceMatrixResult>;
  public async distanceMatrix(
    origin: Coordinates,
    destinations: Coordinates[],
    mode?: GoogleTravelMode | VehicleType,
    signal?: AbortSignal
  ): Promise<DistanceMatrixElement[]>;
  public async distanceMatrix(
    origin: Coordinates,
    destinationOrDestinations: Coordinates | Coordinates[],
    mode?: GoogleTravelMode | VehicleType,
    signal?: AbortSignal
  ): Promise<DistanceMatrixResult | DistanceMatrixElement[]> {
    const isSingle = !Array.isArray(destinationOrDestinations);
    const destinations: Coordinates[] = isSingle
      ? [destinationOrDestinations as Coordinates]
      : (destinationOrDestinations as Coordinates[]);

    if (destinations.length === 0) {
      if (isSingle) {
        return {
          distanceKm: 0,
          distanceMeters: 0,
          durationMinutes: 0,
          durationSeconds: 0,
          status: 'NOT_FOUND',
        };
      }
      return [];
    }

    const originStr = this.formatLatLng(origin);
    const destsStr = destinations.map((d) => this.formatLatLng(d)).join('|');
    const googleMode = this.normalizeMode(mode);

    const endpoint = `${this.baseUrl}/distancematrix/json?origins=${encodeURIComponent(
      originStr
    )}&destinations=${encodeURIComponent(destsStr)}&mode=${encodeURIComponent(
      googleMode
    )}&key=${encodeURIComponent(this.apiKey)}&language=${encodeURIComponent(
      this.language
    )}&region=${encodeURIComponent(this.region)}`;

    const response = await this.fetchWithTimeout<GoogleDistanceMatrixResponse>(endpoint, signal);

    if (response.status !== 'OK') {
      throw new GoogleMapsApiError(
        `Google Distance Matrix failed with status "${response.status}": ${
          response.error_message || ''
        }`.trim(),
        undefined,
        response.status
      );
    }

    const row = response.rows?.[0];
    if (!row || !Array.isArray(row.elements)) {
      throw new GoogleMapsApiError(
        'Google Distance Matrix phản hồi hàng (row) dữ liệu không hợp lệ hoặc rỗng.'
      );
    }

    // Handle single destination 1-1 contract: { distanceKm, distanceMeters, durationMinutes, durationSeconds }
    if (isSingle) {
      const el = row.elements[0];
      if (!el) {
        throw new GoogleMapsApiError('Không tìm thấy element cự ly trong kết quả Google Distance Matrix.');
      }

      if (el.status === 'ZERO_RESULTS' || el.status === 'NOT_FOUND') {
        return {
          distanceKm: 0,
          distanceMeters: 0,
          durationMinutes: 0,
          durationSeconds: 0,
          status: el.status,
          originAddress: response.origin_addresses?.[0] || originStr,
          destinationAddress: response.destination_addresses?.[0] || destsStr,
        };
      }

      if (el.status !== 'OK' || !el.distance || !el.duration) {
        throw new GoogleMapsApiError(
          `Không thể tính khoảng cách giữa hai điểm (Trạng thái: ${el.status}).`,
          undefined,
          el.status
        );
      }

      const distanceMeters = el.distance.value || 0;
      const distanceKm = Number((distanceMeters / 1000).toFixed(2));
      const durationSeconds = el.duration.value || 0;
      const durationMinutes = Math.ceil(durationSeconds / 60);

      return {
        distanceKm,
        distanceMeters,
        durationMinutes,
        durationSeconds,
        status: 'OK',
        originAddress: response.origin_addresses?.[0] || originStr,
        destinationAddress: response.destination_addresses?.[0] || destsStr,
      };
    }

    // Handle batch destinations (LocationOrderManager compatibility)
    return row.elements.map((el) => {
      const distanceMeters = el.distance?.value ?? 0;
      const durationSeconds = el.duration?.value ?? 0;
      return {
        status: el.status,
        distance: {
          text: el.distance?.text || `${(distanceMeters / 1000).toFixed(1)} km`,
          value: distanceMeters,
        },
        duration: {
          text: el.duration?.text || `${Math.ceil(durationSeconds / 60)} phút`,
          value: durationSeconds,
        },
      };
    });
  }

  /**
   * Helper tính khoảng cách 1-1
   */
  public async calculateDistance(
    origin: Coordinates,
    destination: Coordinates,
    mode?: GoogleTravelMode | VehicleType,
    signal?: AbortSignal
  ): Promise<DistanceMatrixResult> {
    return this.distanceMatrix(origin, destination, mode, signal);
  }
}
