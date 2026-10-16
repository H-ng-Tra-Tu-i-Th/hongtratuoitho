/**
 * Goong Maps & Location Order Manager Types
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

export interface GoongPrediction {
  description: string;
  place_id: string;
  reference?: string;
  structured_formatting?: {
    main_text?: string;
    secondary_text?: string;
  };
}

export interface GoongAutoCompleteResponse {
  predictions: GoongPrediction[];
  status: string;
  execution_time?: string;
}

export interface GoongPlaceDetailResult {
  place_id: string;
  formatted_address: string;
  name?: string;
  geometry: {
    location: Coordinates;
  };
}

export interface GoongPlaceDetailResponse {
  result: GoongPlaceDetailResult;
  status: string;
}

export interface GoongGeocodeResult {
  place_id?: string;
  formatted_address: string;
  geometry: {
    location: Coordinates;
  };
}

export interface GoongGeocodeResponse {
  results: GoongGeocodeResult[];
  status: string;
}

export interface GoongDistanceElement {
  status: 'OK' | 'NOT_FOUND' | 'ZERO_RESULTS' | string;
  distance: DistanceInfo;
  duration: DurationInfo;
}

export interface GoongDistanceMatrixRow {
  elements: GoongDistanceElement[];
}

export interface GoongDistanceMatrixResponse {
  rows: GoongDistanceMatrixRow[];
}

export type VehicleType = 'bike' | 'car' | 'taxi' | 'truck';

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

export class GoongApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly apiStatus?: string
  ) {
    super(message);
    this.name = 'GoongApiError';
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
