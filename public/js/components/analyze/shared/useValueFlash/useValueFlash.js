import {useEffect, useRef, useState} from 'react'
import styles from './useValueFlash.module.scss'

export const VALUE_FLASH_DURATION = 400

/**
 * Class names for a flashing element: `base` always (the transition), `active`
 * while `flashing` is true (the highlight background).
 */
export const VALUE_FLASH_CLASS = {
  base: styles.flash,
  active: styles.flashing,
}

/**
 * True for VALUE_FLASH_DURATION ms whenever `value` changes after the first
 * render, so a live update draws the eye. Never flashes on mount, nor while
 * `enabled` is false.
 *
 * Changes are detected with Object.is, so pass a primitive (a number or a
 * string): a new object or array on every render would flash every time.
 *
 * @param {string|number|boolean|null|undefined} value
 * @param {object} [options]
 * @param {boolean} [options.enabled]
 * @returns {boolean} flashing
 */
export const useValueFlash = (value, {enabled = true} = {}) => {
  const [flashing, setFlashing] = useState(false)
  const previousRef = useRef(value)

  useEffect(() => {
    if (Object.is(previousRef.current, value)) return undefined
    previousRef.current = value
    if (!enabled) return undefined

    setFlashing(true)
    const timer = setTimeout(() => setFlashing(false), VALUE_FLASH_DURATION)
    return () => {
      clearTimeout(timer)
      setFlashing(false)
    }
  }, [value, enabled])

  return flashing
}
