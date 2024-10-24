import {useEffect, useRef} from 'react'

import PropTypes from 'prop-types'

/**
 * A React hook that simplify the dom element listeners
 *
 * @param {Object} target
 * @param {string} type
 * @param {Function} listener
 * @param {Function} cleanup
 */
function useEvent(target, type, listener, cleanup) {
  const storedListener = useRef(listener)
  const storedCleanup = useRef(cleanup)

  useEffect(() => {
    storedListener.current = listener
    storedCleanup.current = cleanup
  })

  useEffect(() => {
    const targetEl = target && 'current' in target ? target.current : target
    if (!targetEl) return

    let wasCleaned = false
    function listener(...args) {
      if (wasCleaned) return
      storedListener.current.apply(this, args)
    }

    targetEl.addEventListener(type, listener)
    const cleanup = storedCleanup.current

    return () => {
      wasCleaned = true
      targetEl.removeEventListener(type, listener)
      cleanup && cleanup()
    }
  }, [target, type])
}

useEvent.propTypes = {
  target: PropTypes.object.isRequired,
  type: PropTypes.string.isRequired,
  listener: PropTypes.func.isRequired,
  cleanup: PropTypes.func,
}

export default useEvent
