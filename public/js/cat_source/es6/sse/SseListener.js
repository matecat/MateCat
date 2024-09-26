import {useEffect} from 'react'
import useSse, {ConnectionStates} from './../hooks/useSse'
import useAuth from "../hooks/useAuth";

const SseListener = ({isAuthenticated, userId}) => {

  const {forceLogout} = useAuth()

  const eventHandlers = {
    ack: (data) => {
      console.log('Handling ack:', data)
      // Add your event handling logic here
    },
    logout: (data) => {
      console.log('Handling logout:', data)
      // Add your event handling logic here
      forceLogout() //XXX 
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
