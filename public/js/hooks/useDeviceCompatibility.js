import {useMediaQuery} from './useMediaQuery'

/**
 * A React hook that indicate if user device is compatible
 *
 * @returns {boolean}
 */
function useDeviceCompatibility() {
  const isFinePointerAvailable = useMediaQuery('(any-pointer:fine)')
  const isPrimaryPointerFine = useMediaQuery('(pointer:fine)')
  // const isPrimaryPointerCoarse = useMediaQuery('(pointer:coarse)');
  // const isCoarsePointerAvailable = useMediaQuery('(any-pointer:coarse)');
  // const isHoverAvailable = useMediaQuery('(any-hover:hover)');

  const isNotMobileOrTablet = useMediaQuery('(min-device-width:1024px)')

  // console.log('DEBUG --> isCoarsePointerAvailable:', isCoarsePointerAvailable);
  // console.log('DEBUG --> isHoverAvailable:', isHoverAvailable);
  // console.log('DEBUG --> isPrimaryPointerCoarse:', isPrimaryPointerCoarse);
  // console.log('DEBUG --> isFinePointerAvailable:', isFinePointerAvailable);
  // console.log('DEBUG --> isPrimaryPointerFine:', isPrimaryPointerFine);
  // console.log('DEBUG --> isNotMobileOrTablet:', isNotMobileOrTablet);
  return isPrimaryPointerFine || isFinePointerAvailable || isNotMobileOrTablet
}

export default useDeviceCompatibility
