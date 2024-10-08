import {useCallback, useEffect, useRef, useState} from 'react'

// Object to represent connection states
export const ConnectionStates = {
  CONNECTING: 'CONNECTING',
  OPEN: 'OPEN',
  CLOSED: 'CLOSED',
  ERROR: 'ERROR',
}

const useSse = (url, options, isAuthenticated, eventHandlers = {}) => {
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
    const es = new EventSource(url, options)
    setEventSource(es) // Set the EventSource instance

    es.onopen = () => {
      setConnectionState(ConnectionStates.OPEN) // Update state to OPEN when connection is established
      setConnectionError(null) // Reset the error on successful connection
      if (retryingInterval.current) {
        clearInterval(retryingInterval.current) // Clear the timeout if it was active
        retryingInterval.current = 0
        eventHandlers['reconnected'] ? eventHandlers['reconnected']() : null
      }
    }

    es.onerror = (error) => {
      if (es.readyState === 2) {
        // Only handle reconnection if the connection is closed
        setConnectionState(ConnectionStates.CLOSED) // Update state to CLOSED on error
        setConnectionError(error) // Store the error
        console.error('SSE connection error:', error)

        if (!retryingInterval.current) {
          eventHandlers['disconnected'] ? eventHandlers['disconnected']() : null
          // Attempt to reconnect every 5 seconds
          retryingInterval.current = setInterval(() => {
            console.log('Reconnecting...')
            connect() // Reconnect
          }, 5000)
        }
      }
    }

    // Add listener for the message event
    es.addEventListener('message', (event) => {
      try {
        const parsedData = JSON.parse(event.data) // Parse the incoming JSON message
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
        console.log(`Event raised: ${eventIdentifier}`, parsedData)
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
        clearInterval(retryingInterval.current) // Clear the timeout
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

export default useSse
