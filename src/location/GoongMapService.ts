import {
  Coordinates,
  GoongAutoCompleteResponse,
  GoongDistanceElement,
  GoongDistanceMatrixResponse,
  GoongGeocodeResponse,
  GoongPlaceDetailResponse,
  GoongPrediction,
  GoongApiError,
  VehicleType,
} from './types';

export interface GoongMapServiceConfig {
  apiKey: string;
  baseUrl?: string;
  timeoutMs?: number;
}

/**
 * Service encapsulating 4 Goong Maps REST API endpoints with robust error handling,
 * request timeout, and strict typing.
 */
export class GoongMapService {
  private readonly apiKey: string;
  private readonly baseUrl: string;
  private readonly timeoutMs: number;

  constructor(config: GoongMapServiceConfig) {
    if (!config.apiKey || typeof config.apiKey !== 'string' || !config.apiKey.trim()) {
      throw new GoongApiError('Goong REST API key is required and cannot be empty.');
    }
    this.apiKey = config.apiKey.trim();
    this.baseUrl = (config.baseUrl || 'https://rsapi.goong.io').replace(/\/+$/, '');
    this.timeoutMs = config.timeoutMs && config.timeoutMs > 0 ? config.timeoutMs : 8000;
  }

  /**
   * Helper to perform HTTP GET with timeout and AbortSignal support.
   */
  private async fetchWithTimeout<T>(url: string, externalSignal?: AbortSignal): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);

    // Merge external abort signal if provided
    if (externalSignal) {
      if (externalSignal.aborted) {
        clearTimeout(timer);
        throw new GoongApiError('Request was aborted prior to execution.');
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
        throw new GoongApiError(
          `Goong API HTTP error: ${response.status} ${response.statusText}`,
          response.status
        );
      }

      const data = (await response.json()) as T;
      return data;
    } catch (error: any) {
      if (error.name === 'AbortError') {
        throw new GoongApiError(`Goong API request timed out after ${this.timeoutMs}ms.`);
      }
      if (error instanceof GoongApiError) {
        throw error;
      }
      throw new GoongApiError(error?.message || 'Network request to Goong API failed.');
    } finally {
      clearTimeout(timer);
    }
  }

  /**
   * 1. Place AutoComplete API
   * Endpoint: GET /Place/AutoComplete?input={text}&api_key={KEY}
   * Returns address predictions for user input.
   */
  public async placeAutoComplete(
    input: string,
    signal?: AbortSignal
  ): Promise<GoongPrediction[]> {
    const query = (input || '').trim();
    if (!query) {
      return [];
    }

    const endpoint = `${this.baseUrl}/Place/AutoComplete?input=${encodeURIComponent(
      query
    )}&api_key=${encodeURIComponent(this.apiKey)}`;

    const response = await this.fetchWithTimeout<GoongAutoCompleteResponse>(endpoint, signal);

    if (response.status !== 'OK' && response.status !== 'ZERO_RESULTS') {
      throw new GoongApiError(
        `Place AutoComplete failed with status: ${response.status}`,
        undefined,
        response.status
      );
    }

    return response.predictions || [];
  }

  /**
   * 2. Place Detail API
   * Endpoint: GET /Place/Detail?place_id={place_id}&api_key={KEY}
   * Returns exact coordinates and formatted address for a given place_id.
   */
  public async placeDetail(
    placeId: string,
    signal?: AbortSignal
  ): Promise<{ coordinates: Coordinates; formattedAddress: string }> {
    const id = (placeId || '').trim();
    if (!id) {
      throw new GoongApiError('place_id is required for Place Detail query.');
    }

    const endpoint = `${this.baseUrl}/Place/Detail?place_id=${encodeURIComponent(
      id
    )}&api_key=${encodeURIComponent(this.apiKey)}`;

    const response = await this.fetchWithTimeout<GoongPlaceDetailResponse>(endpoint, signal);

    if (response.status !== 'OK' || !response.result) {
      throw new GoongApiError(
        `Place Detail failed for id "${placeId}" with status: ${response.status}`,
        undefined,
        response.status
      );
    }

    const loc = response.result.geometry?.location;
    if (!loc || typeof loc.lat !== 'number' || typeof loc.lng !== 'number') {
      throw new GoongApiError(`Place Detail result does not contain valid geometry coordinates.`);
    }

    return {
      coordinates: { lat: loc.lat, lng: loc.lng },
      formattedAddress: response.result.formatted_address || '',
    };
  }

  /**
   * 3. Forward Geocoding API
   * Endpoint: GET /geocode?address={address}&api_key={KEY}
   * Converts freeform text address to latitude & longitude coordinates.
   */
  public async forwardGeocode(
    address: string,
    signal?: AbortSignal
  ): Promise<{ coordinates: Coordinates; formattedAddress: string }> {
    const query = (address || '').trim();
    if (!query) {
      throw new GoongApiError('Address string is required for Geocoding.');
    }

    const endpoint = `${this.baseUrl}/geocode?address=${encodeURIComponent(
      query
    )}&api_key=${encodeURIComponent(this.apiKey)}`;

    const response = await this.fetchWithTimeout<GoongGeocodeResponse>(endpoint, signal);

    if (response.status === 'ZERO_RESULTS' || !response.results || response.results.length === 0) {
      throw new GoongApiError(
        `Không tìm thấy tọa độ cho địa chỉ "${query}". Vui lòng kiểm tra lại.`,
        undefined,
        'ZERO_RESULTS'
      );
    }

    if (response.status !== 'OK') {
      throw new GoongApiError(
        `Geocoding failed with status: ${response.status}`,
        undefined,
        response.status
      );
    }

    const first = response.results[0];
    const loc = first.geometry?.location;
    if (!loc || typeof loc.lat !== 'number' || typeof loc.lng !== 'number') {
      throw new GoongApiError(`Geocoding result does not contain valid geometry coordinates.`);
    }

    return {
      coordinates: { lat: loc.lat, lng: loc.lng },
      formattedAddress: first.formatted_address || query,
    };
  }

  /**
   * Reverse Geocoding API
   * Endpoint: GET /Geocode?latlng={lat},{lng}&api_key={KEY}
   * Converts GPS coordinates into a human-readable street address.
   */
  public async reverseGeocode(
    coords: Coordinates,
    signal?: AbortSignal
  ): Promise<{ coordinates: Coordinates; formattedAddress: string }> {
    const latlngStr = `${coords.lat},${coords.lng}`;
    const endpoint = `${this.baseUrl}/Geocode?latlng=${encodeURIComponent(
      latlngStr
    )}&api_key=${encodeURIComponent(this.apiKey)}`;

    const response = await this.fetchWithTimeout<GoongGeocodeResponse>(endpoint, signal);

    const first = response.results?.[0];
    if (!first || !first.formatted_address) {
      throw new GoongApiError(
        `Không thể tìm thấy địa chỉ cho tọa độ (${latlngStr}).`,
        undefined,
        response.status
      );
    }

    return {
      coordinates: first.geometry?.location || coords,
      formattedAddress: first.formatted_address,
    };
  }

  /**
   * 4. Distance Matrix API
   * Endpoint: GET /DistanceMatrix?origins={lat,lng}&destinations={lat1,lng1|lat2,lng2...}&vehicle=bike&api_key={KEY}
   * Calculates actual driving distance and duration from one origin to multiple destinations.
   */
  public async distanceMatrix(
    origin: Coordinates,
    destinations: Coordinates[],
    vehicle: VehicleType = 'bike',
    signal?: AbortSignal
  ): Promise<GoongDistanceElement[]> {
    if (!destinations || destinations.length === 0) {
      return [];
    }

    const originStr = `${origin.lat},${origin.lng}`;
    const destinationsStr = destinations.map((d) => `${d.lat},${d.lng}`).join('|');

    const endpoint = `${this.baseUrl}/DistanceMatrix?origins=${encodeURIComponent(
      originStr
    )}&destinations=${encodeURIComponent(destinationsStr)}&vehicle=${encodeURIComponent(
      vehicle
    )}&api_key=${encodeURIComponent(this.apiKey)}`;

    const response = await this.fetchWithTimeout<GoongDistanceMatrixResponse>(endpoint, signal);

    const row = response.rows?.[0];
    if (!row || !Array.isArray(row.elements)) {
      throw new GoongApiError('Distance Matrix response returned invalid or empty rows.');
    }

    return row.elements;
  }
}
