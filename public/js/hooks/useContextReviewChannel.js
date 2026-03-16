import {useEffect, useRef, useCallback} from 'react'
import PropTypes from 'prop-types'
import ContextReviewChannel from '../utils/contextReviewChannel'

/**
 * React hook that subscribes to incoming ContextReviewChannel messages
 * and provides a stable `sendMessage` function.
 *
 * Thin wrapper around the singleton `ContextReviewChannel` utility,
 * so it can also be used from class components or plain JS via the
 * singleton directly.
 *
 * @param {Object}  [params]
 * @param {Function} [params.onMessage] - Callback invoked when a message is received
 * @returns {{sendMessage: Function}}
 */
function useContextReviewChannel({onMessage} = {}) {
  const onMessageRef = useRef(onMessage)

  useEffect(() => {
    onMessageRef.current = onMessage
  })

  useEffect(() => {
    const off = ContextReviewChannel.onMessage((data) => {
      if (onMessageRef.current) {
        onMessageRef.current(data)
      }
    })
    return off
  }, [])

  const sendMessage = useCallback((message) => {
    ContextReviewChannel.sendMessage(message)
  }, [])

  return {sendMessage}
}

useContextReviewChannel.propTypes = {
  onMessage: PropTypes.func,
}

export default useContextReviewChannel
