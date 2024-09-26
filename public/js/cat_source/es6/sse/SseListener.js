import {useEffect} from 'react'
import useSse, {ConnectionStates} from './../hooks/useSse'

const SseListener = ({isAuthenticated, userId}) => {
  const eventHandlers = {
    myEvent: (data) => {
      console.log('Handling myEvent:', data)
      // Add your event handling logic here
    },
    anotherEvent: (data) => {
      console.log('Handling anotherEvent:', data)
      // Add your event handling logic here
    },
  }
  const getSource = function () {
    let source =
      '/channel/updates' +
      '?jid=' +
      config.id_job +
      '&pw=' +
      config.password +
      '&uid=' +
      userId

    if (config.enableMultiDomainApi) {
      source =
        '//' +
        Math.floor(Math.random() * config.ajaxDomainsNumber) +
        '.ajax.' +
        config.sse_base_url +
        source
    }
    return source
  }
  const {connectionState, connectionError} = useSse(
    getSource(),
    {},
    isAuthenticated,
    eventHandlers,
  )

  useEffect(() => {
    if (connectionState === ConnectionStates.OPEN) {
      console.log('Connection opened')
    }

    if (connectionError) {
      console.error('Connection error:', connectionError)
    }
  }, [connectionState, connectionError])

  return null // No rendering needed
}

export default SseListener
