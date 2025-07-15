import Platform from 'platform'

/**
 * Returns true if the current OS is MacOS or iOS, false otherwise
 *
 * @returns {boolean}
 */
export const isMacOS = () => {
  const os = Platform.os && Platform.os.family
  return (
    os &&
    (os.indexOf('Mac') >= 0 ||
      os.indexOf('OS X') >= 0 ||
      os.indexOf('iOS') >= 0)
  )
}
