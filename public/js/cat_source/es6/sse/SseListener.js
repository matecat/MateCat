import {useEffect} from 'react'
import useSse, {ConnectionStates} from './../hooks/useSse'
import useAuth from "../hooks/useAuth";
import CatToolActions from "../actions/CatToolActions";
import SegmentActions from "../actions/SegmentActions";
import SegmentStore from "../stores/SegmentStore";

const SseListener = ({isAuthenticated, userId}) => {

  const {forceLogout} = useAuth()

  const eventHandlers = {
    ack: (data) => {
      config.id_client = data.clientId
      CatToolActions.clientConnected(data.clientId)
      console.log('Handling ack:', data)

      // Add your event handling logic here
    },
    logout: (data) => {
      console.log('Handling logout:', data)
      // Add your event handling logic here
      forceLogout() //XXX
    },
    glossary_check: (data) => {
      SegmentActions.addQaCheck(data.id_segment, data)
    },
    contribution: ( data ) => {
      if ( config.translation_matches_enabled ) {
          let segment = SegmentStore.getSegmentByIdToJS( data.id_segment )
          if ( segment && segment.splitted ) {
              let segments = SegmentStore.getSegmentsSplitGroup(
                  data.id_segment,
              )
              segments.forEach( function ( item ) {
                  SegmentActions.getContributionsSuccess( data, item.sid )
              } )
          } else if ( segment ) {
              SegmentActions.getContributionsSuccess(
                  data,
                  data.id_segment,
              )
          }
      }
    }
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
