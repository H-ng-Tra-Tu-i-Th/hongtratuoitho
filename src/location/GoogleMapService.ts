import {
  Coordinates,
  GoongDistanceElement,
  GoongGeocodeResponse,
  GoongApiError,
} from './types';

export interface GoogleMapServiceConfig {
  endpoint?: string;
  timeoutMs?: number;
}

/**
 * Client for the server-side Google Maps proxy.
 * The proxy calls Google Geocoding API and Routes Compute Route Matrix,
 * keeping GOOGLE_MAPS_API_KEY out of browser code.
 */
export class GoogleMapService {
  private readonly endpoint: string;
  private readonly timeoutMs: number;

  constructor(config: GoogleMapServiceConfig = {}) {
    this.endpoint = (config.endpoint || '/api/location.php').replace(/\/+$/, '');
    this.timeoutMs = config.timeoutMs && config.timeoutMs > 0 ? config.timeoutMs : 8000;
  }

  private async request<T>(
    action: string,
    params: Record<string, string>,
    signal?: AbortSignal
  ): Promise<T> {
    const query = new URLSearchParams({ action, ...params });
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);
    const abort = () => controller.abort();
    signal?.addEventListener('abort', abort, { once: true });

    try {
      const response = await fetch(`${this.endpoint}?${query.toString()}`, {
        headers: { Accept: 'application/json' },
        signal: controller.signal,
      });
      const data = (await response.json()) as T & { message?: string };
      if (!response.ok) {
        throw new GoongApiError(data.message || `Google Maps API HTTP error: ${response.status}`, response.status);
      }
      return data;
    } catch (error: unknown) {
      if (error instanceof Error && error.name === 'AbortError') {
        throw new GoongApiError(`Google Maps API request timed out after ${this.timeoutMs}ms.`);
      }
      if (error instanceof GoongApiError) {
        throw error;
      }
      throw new GoongApiError(error instanceof Error ? error.message : 'Google Maps API request failed.');
    } finally {
      clearTimeout(timer);
      signal?.removeEventListener('abort', abort);
    }
  }

  public async forwardGeocode(
    address: string,
    signal?: AbortSignal
  ): Promise<{ coordinates: Coordinates; formattedAddress: string }> {
    const query = address.trim();
    if (!query) throw new GoongApiError('Address string is required for geocoding.');
    const response = await this.request<GoongGeocodeResponse>('geocode', { address: query }, signal);
    const first = response.results?.[0];
    const location = first?.geometry?.location;
    if (!first || !location) {
      throw new GoongApiError(`Không tìm thấy tọa độ cho địa chỉ "${query}".`, undefined, 'ZERO_RESULTS');
    }
    return { coordinates: location, formattedAddress: first.formatted_address || query };
  }

  public async reverseGeocode(
    coords: Coordinates,
    signal?: AbortSignal
  ): Promise<{ coordinates: Coordinates; formattedAddress: string }> {
    const response = await this.request<GoongGeocodeResponse>(
      'reverse_geocode',
      { lat: String(coords.lat), lng: String(coords.lng) },
      signal
    );
    const first = response.results?.[0];
    if (!first?.formatted_address) {
      throw new GoongApiError(`Không thể tìm thấy địa chỉ cho tọa độ (${coords.lat},${coords.lng}).`);
    }
    return {
      coordinates: first.geometry?.location || coords,
      formattedAddress: first.formatted_address,
    };
  }

  public async distanceMatrix(
    origin: Coordinates,
    destinations: Coordinates[],
    _vehicle = 'bike',
    signal?: AbortSignal
  ): Promise<GoongDistanceElement[]> {
    if (!destinations.length) return [];
    const coords = (point: Coordinates) => `${point.lat},${point.lng}`;
    const response = await this.request<{ rows?: Array<{ elements?: GoongDistanceElement[] }> }>(
      'distance_matrix',
      {
        origins: coords(origin),
        destinations: destinations.map(coords).join('|'),
      },
      signal
    );
    return response.rows?.[0]?.elements || [];
  }
}
