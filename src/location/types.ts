/**
 * Maps & Location Order Manager Types
 * Standardized contracts for Google Maps Platform API.
 */

export interface Coordinates {
  lat: number;
  lng: number;
}

export interface Store {
  id: number;
  name: string;
  address: string;
  phone?: string;
  lat: number | null;
  lng: number | null;
  is_active: boolean;
}

export interface DistanceInfo {
  text: string;
  value: number; // in meters
}

export interface DurationInfo {
  text: string;
  value: number; // in seconds
}

export interface StoreWithDistance extends Store {
  distance?: DistanceInfo;
  duration?: DurationInfo;
  matrixStatus?: 'OK' | 'NOT_FOUND' | 'ZERO_RESULTS' | 'ERROR' | 'NO_COORDINATES';
}

/**
 * 1. FORWARD_GEOCODE Output Contract:
 * Nhận địa chỉ text -> Trả về { lat, lng, formattedAddress }
 */
export interface ForwardGeocodeResult {
  lat: number;
  lng: number;
  formattedAddress: string;
  coordinates?: Coordinates;
}

/**
 * 2. REVERSE_GEOCODE Output Contract:
 * Nhận { lat, lng } -> Trả về { formattedAddress, lat, lng }
 */
export interface ReverseGeocodeResult {
  formattedAddress: string;
  lat: number;
  lng: number;
  coordinates?: Coordinates;
}

/**
 * 3. DISTANCE_MATRIX Output Contract:
 * Nhận 1 điểm xuất phát (Quán) và 1 điểm đến (Khách) ->
 * Trả về { distanceKm, distanceMeters, durationMinutes, durationSeconds }
 */
export interface DistanceMatrixResult {
  distanceKm: number;
  distanceMeters: number;
  durationMinutes: number;
  durationSeconds: number;
  status?: string;
  originAddress?: string;
  destinationAddress?: string;
}

export interface DistanceMatrixElement {
  status: 'OK' | 'NOT_FOUND' | 'ZERO_RESULTS' | string;
  distance: DistanceInfo;
  duration: DurationInfo;
}

/** Travel mode mapping: Google Distance Matrix REST API supports driving, bicycling, walking, transit */
export type GoogleTravelMode = 'driving' | 'bicycling' | 'walking' | 'transit' | 'two_wheeler';
export type VehicleType = 'bike' | 'car' | 'taxi' | 'truck' | 'two_wheeler' | 'driving' | 'bicycling' | 'walking' | 'transit';

export interface GoogleMapsConfig {
  apiKey: string;
  baseUrl?: string;
  timeoutMs?: number;
  language?: string; // default 'vi'
  region?: string;   // default 'vn'
  defaultMode?: GoogleTravelMode;
}

export interface GoogleGeocodeGeometry {
  location: Coordinates;
  location_type?: string;
}

export interface GoogleGeocodeResult {
  place_id?: string;
  formatted_address: string;
  geometry: GoogleGeocodeGeometry;
  address_components?: Array<{
    long_name: string;
    short_name: string;
    types: string[];
  }>;
  types?: string[];
}

export interface GoogleGeocodeResponse {
  results: GoogleGeocodeResult[];
  status: 'OK' | 'ZERO_RESULTS' | 'OVER_QUERY_LIMIT' | 'REQUEST_DENIED' | 'INVALID_REQUEST' | 'UNKNOWN_ERROR' | string;
  error_message?: string;
}

export interface GoogleDistanceMatrixElement {
  status: 'OK' | 'NOT_FOUND' | 'ZERO_RESULTS' | 'MAX_ROUTE_LENGTH_EXCEEDED' | string;
  distance?: DistanceInfo;
  duration?: DurationInfo;
}

export interface GoogleDistanceMatrixRow {
  elements: GoogleDistanceMatrixElement[];
}

export interface GoogleDistanceMatrixResponse {
  destination_addresses: string[];
  origin_addresses: string[];
  rows: GoogleDistanceMatrixRow[];
  status: 'OK' | 'INVALID_REQUEST' | 'MAX_ELEMENTS_EXCEEDED' | 'OVER_QUERY_LIMIT' | 'REQUEST_DENIED' | 'UNKNOWN_ERROR' | string;
  error_message?: string;
}

export interface LocationOrderState {
  userLocation: Coordinates | null;
  userAddress: string;
  locationSource: 'gps' | 'autocomplete' | 'manual_geocode' | null;
  storesWithDistance: StoreWithDistance[];
  selectedStore: StoreWithDistance | null;
  nearestStore: StoreWithDistance | null;
  isLoading: boolean;
  isMatrixCalculating: boolean;
  error: string | null;
}

// Map Error Hierarchy
export class MapApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly apiStatus?: string,
    public readonly provider: string = 'GoogleMaps'
  ) {
    super(message);
    this.name = 'MapApiError';
  }
}

export class GoogleMapsApiError extends MapApiError {
  constructor(
    message: string,
    statusCode?: number,
    apiStatus?: string
  ) {
    super(message, statusCode, apiStatus, 'GoogleMaps');
    this.name = 'GoogleMapsApiError';
  }
}

export class GeolocationError extends Error {
  constructor(
    message: string,
    public readonly code?: number
  ) {
    super(message);
    this.name = 'GeolocationError';
  }
}
