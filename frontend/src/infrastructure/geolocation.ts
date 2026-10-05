/**
 * Queries the device's location (K-02, E-04).
 */

export class LocationError extends Error {
  constructor(readonly reason: 'unsupported' | 'failed') {
    super(`geolocation ${reason}`);
    this.name = 'LocationError';
  }
}

export interface Locator {
  locate(): Promise<{ lon: number; lat: number }>;
}

const OPTIONS: PositionOptions = { enableHighAccuracy: false, timeout: 10_000, maximumAge: 60_000 };

export function createLocator(geolocation: Geolocation | undefined): Locator {
  return {
    locate: () =>
      new Promise((resolve, reject) => {
        if (geolocation === undefined) {
          reject(new LocationError('unsupported'));
          return;
        }
        geolocation.getCurrentPosition(
          (position) => resolve({ lon: position.coords.longitude, lat: position.coords.latitude }),
          () => reject(new LocationError('failed')),
          OPTIONS,
        );
      }),
  };
}
