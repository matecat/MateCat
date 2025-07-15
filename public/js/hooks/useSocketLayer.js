import {useCallback, useEffect, useRef, useState} from 'react'
import {getSocketAuthToken} from '../api/loginUser'

const {io} = require('socket.io-client')

// Object to represent connection states
export const ConnectionStates = {
  CONNECTING: 'CONNECTING',
  OPEN: 'OPEN',
  CLOSED: 'CLOSED',
  ERROR: 'ERROR',
}

const useSocketLayer = (
  connectionParams,
  options,
  isAuthenticated,
  eventHandlers = {},
) => {
  // State variables to manage connection status, error, event source, and received event data
  const [connectionState, setConnectionState] = useState(
    ConnectionStates.CONNECTING,
  )
  const [connectionError, setConnectionError] = useState(null)
  const [eventSource, setEventSource] = useState(null)
  const [eventData, setEventData] = useState({})

  const retryingInterval = useRef()

  const dispatchEventNotification = (type, payload) => {
    const event = new CustomEvent(type, {detail: payload})
    document.dispatchEvent(event)
  }

  const connect = () => {
    getSocketAuthToken()
      .then((response) => {
        connectUnderlyingSocket({
          'x-token': response.token,
          'x-uuid': options.uuidV4,
          'x-userid': options.userId,
          'x-jobid': options.jobId,
        })
      })
      .catch((error) => {
        console.log('Token error', error)
        reconnect()
      })
  }

  const reconnect = () => {
    eventHandlers['disconnected'] ? eventHandlers['disconnected']() : null
    // Attempt to reconnect every 5 seconds
    if (!retryingInterval.current) {
      retryingInterval.current = setTimeout(() => {
        console.log('Reconnecting...')
        clearTimeout(retryingInterval.current)
        retryingInterval.current = null
        connect() // Reconnect
      }, 2000)
    }
  }

  const connectUnderlyingSocket = (extraHeaders) => {
    const socket = io(connectionParams.source, {
      path: connectionParams.path,
      reconnection: false, // manually handle reconnections
      extraHeaders: extraHeaders,
      transports: ['websocket', 'polling'],
      auth: extraHeaders,
    })

    setEventSource(socket) // Set the EventSource instance
    socket.on('connect', () => {
      setConnectionState(ConnectionStates.OPEN) // Update state to OPEN when connection is established
      setConnectionError(null) // Reset the error on successful connection
      if (retryingInterval.current) {
        clearTimeout(retryingInterval.current) // Clear the timeout if it was active
        retryingInterval.current = null
        eventHandlers['reconnected'] ? eventHandlers['reconnected']() : null
      }
    })

    // CLIENT CODE: The server has forcefully disconnected the socket with socket.disconnect(),
    // the client will not try to reconnect and, you need to manually call socket.connect().
    socket.on('disconnect', function () {
      reconnect()
    })

    socket.on('connect_error', (error) => {
      /*
       * connect_error
       * This event is fired when:
       *
       *  - the low-level connection cannot be established
       *  - the connection is denied by the server in a middleware function
       *
       * In the first case, the Socket will automatically try to reconnect, after a given delay.
       * In the latter case, you need to manually reconnect. You might need to update the credentials.
       *
       * Since we cannot distinguish between the two cases, we manually handle the reconnections
       *
       */
      setConnectionState(ConnectionStates.CLOSED) // Update state to CLOSED on error
      setConnectionError(error) // Store the error
      console.error('Socket connection error:', error)
      reconnect()
    })

    // Add listener for the message event
    socket.on('message', (event) => {
      try {
        const parsedData = event.data // Parse the incoming JSON message
        const {_type: eventIdentifier} = parsedData // Extract event identifier and data

        // Update state with the received data
        setEventData((prevData) => ({
          ...prevData,
          [eventIdentifier]: parsedData, // Use the event identifier as the key
        }))

        // Check if the eventIdentifier matches any of the provided event handlers
        if (eventHandlers[eventIdentifier]) {
          eventHandlers[eventIdentifier](parsedData) // Call the associated handler with the data
        }
        dispatchEventNotification(eventIdentifier, parsedData)
        // Log the raised event (optional)
      } catch (error) {
        console.error('Error parsing message:', error) // Handle parsing errors
      }
    })
  }

  useEffect(() => {
    if (isAuthenticated) {
      connect() // Initialize the connection if authenticated
    } else {
      // Cleanup: close the connection if the user is not authenticated
      if (eventSource) {
        eventSource.close() // Close the EventSource
        setEventSource(null) // Reset the eventSource state
      }
      setConnectionState(ConnectionStates.CLOSED) // Set state to CLOSED
    }

    // Cleanup: close the connection on unmount
    return () => {
      if (eventSource) {
        eventSource.close() // Close the EventSource
      }
      if (retryingInterval.current) {
        clearTimeout(retryingInterval.current) // Clear the timeout
      }
    }
  }, [isAuthenticated]) // Reconnect if authentication state changes

  const closeConnection = useCallback(() => {
    if (eventSource) {
      eventSource.close() // Close the EventSource
      setEventSource(null) // Reset the eventSource state
      retryingInterval.current = undefined // Reset the retry value when manually closing the connection
    }
  }, [eventSource])

  // Return the connection state, error, close function, and received event data
  return {
    connectionState,
    connectionError,
    closeConnection,
    eventData,
  }
}

export default useSocketLayer
