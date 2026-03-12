import {useContext, useEffect} from 'react'
import useSocketLayer, {ConnectionStates} from '../hooks/useSocketLayer'
import CatToolActions from '../actions/CatToolActions'
import SegmentActions from '../actions/SegmentActions'
import SegmentStore from '../stores/SegmentStore'
import CommonUtils from '../utils/commonUtils'
import CommentsActions from '../actions/CommentsActions'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import UserActions from '../actions/UserActions'
import {v4 as uuidV4} from 'uuid'
import Cookies from 'js-cookie'

const SocketListener = ({isAuthenticated, userId}) => {
  const {forceLogout} = useContext(ApplicationWrapperContext)
  const eventHandlers = {
    disconnected: () => {
      CatToolActions.clientConnected(false)
    },
    reconnected: () => {
      CatToolActions.clientReconnect()
    },
    ack: ({clientId, serverVersion}) => {
      config.id_client = clientId
      CatToolActions.clientConnected(clientId)
      if (serverVersion !== config.build_number) {
        const notification = {
          title: 'New update available!',
          text:
            'We’ve just released an update with improvements and bug fixes.<br/>' +
            'To ensure all changes are applied correctly, we recommend refreshing the page.<br/><br/>' +
            'Click Refresh or press <strong>Ctrl+R</strong> (Windows) / <strong>Cmd+R</strong> (Mac).<br/><br/>' +
            'Thank you for using Matecat!',
          type: 'warning',
          allowHtml: true,
        }
        CatToolActions.addNotification(notification)
      }
      // Add your event handling logic here
    },
    force_reload: () => {
      UserActions.forceReload()
    },
    concordance: (data) => {
      SegmentActions.setConcordanceResult(data.id_segment, data)
    },
    bulk_segment_status_change: (data) => {
      SegmentActions.bulkChangeStatusCallback(data.segment_ids, data.status)
    },
    glossary_get: (data) => {
      if (!Array.isArray(data.terms)) {
        const trackingMessage = `Glossary GET terms is not Array: ${
          data.terms
        } / message: ${JSON.stringify(data)}`
        CommonUtils.dispatchTrackingError(trackingMessage)
      }

      const terms = Array.isArray(data.terms) ? data.terms : []
      const blacklistedTerms = Array.isArray(data.blacklisted_terms)
        ? data.blacklisted_terms
        : []

      SegmentActions.setGlossaryForSegment(data.id_segment, [
        ...terms,
        ...blacklistedTerms.map((term) => ({
          ...term,
          isBlacklist: true,
        })),
      ])
    },
    glossary_set: (data) => {
      if (data.error) {
        SegmentActions.errorAddGlossaryItemToCache(data.id_segment, data.error)
      } else {
        SegmentActions.addGlossaryItemToCache(data.id_segment, data.payload)
      }
    },
    glossary_delete: (data) => {
      if (data.error) {
        SegmentActions.errorDeleteGlossaryFromCache(data.id_segment, data.error)
      } else {
        SegmentActions.deleteGlossaryFromCache(
          data.id_segment,
          data.payload.term,
        )
      }
    },
    glossary_update: (data) => {
      if (data.error) {
        SegmentActions.errorUpdateglossaryCache(data.id_segment, data.error)
      } else {
        SegmentActions.updateglossaryCache(data.id_segment, data.payload)
      }
    },
    glossary_domains: (data) => {
      CatToolActions.setDomains({
        sid: data.id_segment,
        ...data,
      })
    },
    glossary_search: (data) => {
      const mergedTerms = [
        ...data.terms,
        ...data.blacklisted_terms.map((term) => ({
          ...term,
          isBlacklist: true,
        })),
      ]

      // swap source and target props of terms if user searching in target
      const terms = SegmentStore.isSearchingGlossaryInTarget
        ? mergedTerms.map((term) => ({
            ...term,
            source: term.target,
            target: term.source,
            source_language: term.target_language,
            target_language: term.source_language,
          }))
        : mergedTerms
      SegmentActions.setGlossaryForSegmentBySearch(data.id_segment, terms)
    },
    glossary_check: (data) => {
      SegmentActions.addQaCheck(data.id_segment, data)
    },
    glossary_keys: (data) => {
      CatToolActions.setHaveKeysGlossary(data.has_glossary)
    },
    comment: (data) => {
      CommentsActions.updateCommentsFromSse(data)
    },
    contribution: (data) => {
      if (config.translation_matches_enabled) {
        const segment = SegmentStore.getSegmentByIdToJS(data.id_segment)
        if (segment && segment.splitted) {
          const segments = SegmentStore.getSegmentsSplitGroup(data.id_segment)
          segments.forEach(function (item) {
            SegmentActions.getContributionsSuccess(data, item.sid)
          })
        } else if (segment) {
          SegmentActions.getContributionsSuccess(data, data.id_segment)
        }
      }
    },
    cross_language_matches: (data) => {
      if (config.translation_matches_enabled) {
        const segment = SegmentStore.getSegmentByIdToJS(data.id_segment)
        if (segment && segment.splitted) {
          const segments = SegmentStore.getSegmentsSplitGroup(data.id_segment)
          segments.forEach(function (item) {
            SegmentActions.setSegmentCrossLanguageContributions(
              item.sid,
              segment.id_file,
              data.matches,
              [],
            )
          })
        } else if (segment) {
          SegmentActions.setSegmentCrossLanguageContributions(
            data.id_segment,
            segment.id_file,
            data.matches,
            [],
          )
        }
      }
    },
    ai_assistant_explain_meaning: (data) => {
      SegmentActions.aiSuggestion({
        sid: data.id_segment,
        suggestion: data.message,
        isCompleted: data.completed,
        hasError: Boolean(data?.has_error),
      })
    },
    ai_assistant_alternative_translations: (data) => {
      SegmentActions.aiAlternativeSuggestion({
        sid: data.id_segment,
        data,
      })
    },
    ai_assistant_feedback: (data) => {
      SegmentActions.aiFeedbackSuggestion({
        sid: data.id_segment,
        data,
      })
    },
    global_messages: (data) => {
      const message = data.message
      if (
        message &&
        typeof Cookies.get('msg-' + message.token) == 'undefined' &&
        new Date(message.expire) > new Date()
      ) {
        const notification = {
          uid: message.token,
          title: message.title ? message.title : 'Notice',
          text: message.message,
          type: message.level ? message.level : 'warning',
          autoDismiss: false,
          position: 'bl',
          allowHtml: true,
          closeCallback: function () {
            const expireDate = new Date(message.expire)
            Cookies.set('msg-' + message.token, '', {
              expires: expireDate,
              secure: true,
            })
          },
        }
        CatToolActions.addNotification(notification)
      }
    },
    quota_exceeded: () => {
      CatToolActions.showLaraQuotaExceeded()
    },
    logout: (data) => {
      console.log('Handling logout:', data)
      // Add your event handling logic here
      forceLogout()
    },
  }

  const getSource = function () {
    let source = window.location.host

    if (config.enableMultiDomainApi) {
      source =
        Math.floor(Math.random() * config.ajaxDomainsNumber) + '.ajax.' + source
    }

    return {source: '//' + source, path: '/sse/channel/updates/socket.io'}
  }

  const {connectionState, connectionError} = useSocketLayer(
    getSource(),
    {userId: userId?.toString(), uuidV4: uuidV4(), jobId: config.id_job},
    isAuthenticated,
    eventHandlers,
  )

  useEffect(() => {
    if (connectionState === ConnectionStates.OPEN) {
      // console.log('Connection opened')
    }

    if (connectionError) {
      console.error('Connection error:', connectionError)
    }
  }, [connectionState, connectionError])

  return null // No rendering needed
}

export default SocketListener
