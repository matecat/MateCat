import CatToolActions from '../actions/CatToolActions'
import SegmentActions from '../actions/SegmentActions'
import SegmentStore from '../stores/SegmentStore'
import CommonUtils from '../utils/commonUtils'
import CommentsActions from '../actions/CommentsActions'

let SSE = {
  init: function () {
    // TODO configure this
    this.baseURL = config.sse_base_url
    this.clientConnected = false
    this.disconnect = false
    this.initEvents()
  },
  getSource: function (what) {
    var source = ''
    switch (what) {
      case 'notifications':
        source =
          '/channel/updates' +
          '?jid=' +
          config.id_job +
          '&pw=' +
          config.password
        break

      default:
        throw new Exception('source mapping not found')
    }

    if (config.enableMultiDomainApi) {
      return new EventSource(
        '//' +
          Math.floor(Math.random() * config.ajaxDomainsNumber) +
          '.ajax.' +
          SSE.baseURL +
          source,
      )
    } else {
      return new EventSource('//' + SSE.baseURL + source)
    }
  },
  initEvents: function () {
    $(document).on('sse:ack', function (ev, message) {
      SSE.clientConnected = true
      config.id_client = message.data.clientId
      CatToolActions.clientConnected(message.data.clientId)
      if (SSE.disconnect) {
        SSE.disconnect = false
        CatToolActions.clientReconnect()
      }
    })
    $(document).on('sse:concordance', function (ev, message) {
      SegmentActions.setConcordanceResult(message.data.id_segment, message.data)
    })

    $(document).on('sse:bulk_segment_status_change', function (ev, message) {
      SegmentActions.bulkChangeStatusCallback(
        message.data.segment_ids,
        message.data.status,
      )
    })
    $(document).on('sse:glossary_get', function (ev, message) {
      if (!Array.isArray(message.data.terms)) {
        const trackingMessage = `Glossary GET terms is not Array: ${
          message.data.terms
        } / message: ${JSON.stringify(message)}`
        CommonUtils.dispatchTrackingError(trackingMessage)
      }

      const terms = Array.isArray(message.data.terms) ? message.data.terms : []
      const blacklistedTerms = Array.isArray(message.data.blacklisted_terms)
        ? message.data.blacklisted_terms
        : []

      SegmentActions.setGlossaryForSegment(message.data.id_segment, [
        ...terms,
        ...blacklistedTerms.map((term) => ({
          ...term,
          isBlacklist: true,
        })),
      ])
    })
    $(document).on('sse:glossary_set', function (ev, message) {
      if (message.data.error) {
        SegmentActions.errorAddGlossaryItemToCache(
          message.data.id_segment,
          message.data.error,
        )
      } else {
        SegmentActions.addGlossaryItemToCache(
          message.data.id_segment,
          message.data.payload,
        )
      }
    })
    $(document).on('sse:glossary_delete', function (ev, message) {
      if (message.data.error) {
        SegmentActions.errorDeleteGlossaryFromCache(
          message.data.id_segment,
          message.data.error,
        )
      } else {
        SegmentActions.deleteGlossaryFromCache(
          message.data.id_segment,
          message.data.payload.term,
        )
      }
    })
    $(document).on('sse:glossary_update', function (ev, message) {
      if (message.data.error) {
        SegmentActions.errorUpdateglossaryCache(
          message.data.id_segment,
          message.data.error,
        )
      } else {
        SegmentActions.updateglossaryCache(
          message.data.id_segment,
          message.data.payload,
        )
      }
    })
    $(document).on('sse:glossary_domains', function (ev, message) {
      CatToolActions.setDomains({
        sid: message.data.id_segment,
        ...message.data,
      })
    })
    $(document).on('sse:glossary_search', function (ev, message) {
      const mergedTerms = [
        ...message.data.terms,
        ...message.data.blacklisted_terms.map((term) => ({
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
      SegmentActions.setGlossaryForSegmentBySearch(
        message.data.id_segment,
        terms,
      )
    })
    $(document).on('sse:glossary_check', function (ev, message) {
      SegmentActions.addQaCheck(message.data.id_segment, message.data)
    })
    $(document).on('sse:glossary_keys', function (ev, message) {
      CatToolActions.setHaveKeysGlossary(message.data.has_glossary)
    })
    $(document).on('sse:comment', function (ev, message) {
      CommentsActions.updateCommentsFromSse(message.data)
    })
    if (config.translation_matches_enabled) {
      $(document).on('sse:contribution', function (ev, message) {
        var segment = SegmentStore.getSegmentByIdToJS(message.data.id_segment)
        if (segment && segment.splitted) {
          var segments = SegmentStore.getSegmentsSplitGroup(
            message.data.id_segment,
          )
          segments.forEach(function (item) {
            SegmentActions.getContributionsSuccess(message.data, item.sid)
          })
        } else if (segment) {
          SegmentActions.getContributionsSuccess(
            message.data,
            message.data.id_segment,
          )
        }
      })

      $(document).on('sse:cross_language_matches', function (ev, message) {
        var segment = SegmentStore.getSegmentByIdToJS(message.data.id_segment)
        if (segment && segment.splitted) {
          var segments = SegmentStore.getSegmentsSplitGroup(
            message.data.id_segment,
          )
          segments.forEach(function (item) {
            SegmentActions.setSegmentCrossLanguageContributions(
              item.sid,
              segment.id_file,
              message.data.matches,
              [],
            )
          })
        } else if (segment) {
          SegmentActions.setSegmentCrossLanguageContributions(
            message.data.id_segment,
            segment.id_file,
            message.data.matches,
            [],
          )
        }
      })
    }
    $(document).on('sse:ai_assistant_explain_meaning', function (ev, message) {
      SegmentActions.aiSuggestion({
        sid: message.data.id_segment,
        suggestion: message.data.message,
        isCompleted: message.data.completed,
        hasError: Boolean(message.data?.has_error),
      })
    })
  },

  Message: function (data) {
    this._type = data._type
    this.data = data
    this.types = [
      'comment',
      'ack',
      'contribution',
      'concordance',
      'bulk_segment_status_change',
      'cross_language_matches',
      'glossary_get',
      'glossary_set',
      'glossary_delete',
      'glossary_update',
      'glossary_domains',
      'glossary_search',
      'glossary_check',
      'glossary_keys',
      'ai_assistant_explain_meaning',
    ]
    this.eventIdentifier = 'sse:' + this._type

    this.isValid = function () {
      return this.types.indexOf(this._type) !== -1
    }
  },
}

const NOTIFICATIONS = {
  start: function () {
    SSE.init()
    this.source = SSE.getSource('notifications')

    this.addEvents()
  },
  restart: function () {
    this.source = SSE.getSource('notifications')
    this.addEvents()
  },
  addEvents: function () {
    var self = this
    this.source.addEventListener(
      'message',
      (e) => {
        var message = new SSE.Message(JSON.parse(e.data))
        if (message.isValid()) {
          $(document).trigger(message.eventIdentifier, message)
        }
      },
      false,
    )

    this.source.addEventListener(
      'error',
      () => {
        console.error('SSE: server disconnect')
        if (SSE.clientConnected) {
          SSE.clientConnected = false
          SSE.disconnect = true
          setTimeout(() => {
            if (!SSE.clientConnected) {
              config.id_client = undefined
              CatToolActions.clientConnected()
            }
          }, 5000)
        }

        // console.log( "readyState: " + NOTIFICATIONS.source.readyState );
        if (NOTIFICATIONS.source.readyState === 2) {
          setTimeout(function () {
            // console.log( "Restart Event Source" );
            self.source.close()
            self.restart()
          }, 5000)
        }
      },
      false,
    )
    this.source.addEventListener('open', () => {}, false)
  },
}

export default NOTIFICATIONS
