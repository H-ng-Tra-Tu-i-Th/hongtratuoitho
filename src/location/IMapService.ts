import {
  Coordinates,
  DistanceMatrixElement,
  DistanceMatrixResult,
  ForwardGeocodeResult,
  GoogleTravelMode,
  ReverseGeocodeResult,
  VehicleType,
} from './types';

/**
 * Target Interface for Map Services (Adapter Pattern).
 * Defines standardized contracts for the 3 core business operations,
 * allowing seamless switching between Google Maps Platform, Goong Maps, or other providers.
 */
export interface IMapService {
  /**
   * Name of current underlying map provider ('GoogleMaps' | 'GoongMaps' | ...)
   */
  readonly providerName: string;

  /**
   * 1. FORWARD_GEOCODE:
   * Nhận địa chỉ text -> Trả về { lat, lng, formattedAddress }
   *
   * @param address Chuỗi địa chỉ cần tìm tọa độ
   * @param signal Tùy chọn AbortSignal để hủy request
   */
  forwardGeocode(
    address: string,
    signal?: AbortSignal
  ): Promise<ForwardGeocodeResult>;

  /**
   * 2. REVERSE_GEOCODE:
   * Nhận { lat, lng } -> Trả về { formattedAddress, lat, lng }
   *
   * @param coords Tọa độ { lat, lng } cần dịch sang địa chỉ văn bản
   * @param signal Tùy chọn AbortSignal để hủy request
   */
  reverseGeocode(
    coords: Coordinates,
    signal?: AbortSignal
  ): Promise<ReverseGeocodeResult>;

  /**
   * 3. DISTANCE_MATRIX (1 điểm xuất phát -> 1 điểm đến):
   * Nhận 1 điểm xuất phát (Quán) và 1 điểm đến (Khách)
   * -> Trả về { distanceKm, distanceMeters, durationMinutes, durationSeconds }
   */
  distanceMatrix(
    origin: Coordinates,
    destination: Coordinates,
    mode?: GoogleTravelMode | VehicleType,
    signal?: AbortSignal
  ): Promise<DistanceMatrixResult>;

  /**
   * DISTANCE_MATRIX Batch (1 điểm xuất phát -> N điểm đến):
   * Phục vụ tính toán và so sánh cự ly đa chi nhánh cho LocationOrderManager
   */
  distanceMatrix(
    origin: Coordinates,
    destinations: Coordinates[],
    mode?: GoogleTravelMode | VehicleType,
    signal?: AbortSignal
  ): Promise<DistanceMatrixElement[]>;

  /**
   * Union overload signature
   */
  distanceMatrix(
    origin: Coordinates,
    destinationOrDestinations: Coordinates | Coordinates[],
    mode?: GoogleTravelMode | VehicleType,
    signal?: AbortSignal
  ): Promise<DistanceMatrixResult | DistanceMatrixElement[]>;

  /**
   * Helper hàm tính khoảng cách 1-1 tường minh
   */
  calculateDistance?(
    origin: Coordinates,
    destination: Coordinates,
    mode?: GoogleTravelMode | VehicleType,
    signal?: AbortSignal
  ): Promise<DistanceMatrixResult>;
}
