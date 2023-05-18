import React, {useEffect, useState, useRef} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import IconLike from '../icons/IconLike'
import IconDislike from '../icons/IconDislike'
import {aiSuggestion} from '../../api/aiSuggestion/aiSuggestion'
import TagUtils from '../../utils/tagUtils'
import CommonUtils from '../../utils/commonUtils'
import {TabConcordanceResults} from './TabConcordanceResults'
import {getConcordance} from '../../api/getConcordance'
import OfflineUtils from '../../utils/offlineUtils'

let memoSuggestions = []

export const SegmentFooterTabAiAssistant = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [suggestion, setSuggestion] = useState(undefined)
  const [hasError, setHasError] = useState(false)
  const [feedbackLeave, setFeedbackLeave] = useState()
  const [isLoadingTmMatches, setIsLoadingTmMatches] = useState(false)

  const requestedWord = useRef()
  const resultsRef = useRef()

  const sendFeedback = (feedback) => {
    const message = {
      sid: segment.sid,
      segment: segment.decodedSource,
      request: requestedWord.current,
      response: suggestion?.value,
      source: config.source_code,
      target: config.target_code,
      feedback: feedback ? 'Yes' : 'No',
    }
    CommonUtils.dispatchTrackingEvents('AiAssistantFeedback', message)
    setFeedbackLeave(feedback ? 'Yes' : 'No')
  }

  useEffect(() => {
    memoSuggestions = memoSuggestions.filter(
      ({idSegment}) => idSegment === segment.sid,
    )

    // get TM matches loader when receive message
    const tmMatchesLoadCompleted = () => setIsLoadingTmMatches(false)

    SegmentStore.addListener(
      SegmentConstants.CONCORDANCE_RESULT,
      tmMatchesLoadCompleted,
    )

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.CONCORDANCE_RESULT,
        tmMatchesLoadCompleted,
      )
    }
  }, [segment.sid])

  useEffect(() => {
    let isCachedLastRequest = false

    const aiAssistantHandler = ({sid, value}) => {
      if (sid === segment.sid) {
        setSuggestion(undefined)
        setHasError(false)
        setFeedbackLeave(undefined)
        requestedWord.current = value

        const sourceContent = segment.decodedSource

        memoSuggestions = memoSuggestions.filter(({suggestion}) => suggestion)

        const cacheNameKey = `${segment.sid}-${value}`
        const cacheSuggestion = memoSuggestions.find(
          ({key}) => key === cacheNameKey,
        )

        // request tm matches
        getConcordance(requestedWord.current, 0).catch(() => {
          OfflineUtils.failedConnection(this, 'getConcordance')
        })
        setIsLoadingTmMatches(true)
        resultsRef?.current?.reset()

        // check suggestions cache
        if (cacheSuggestion?.suggestion) {
          setSuggestion({value: cacheSuggestion.suggestion, isCompleted: true})
          isCachedLastRequest = true
          return
        } else {
          isCachedLastRequest = false
        }

        aiSuggestion({
          idSegment: segment.sid,
          words: value,
          phrase: sourceContent,
        })

        memoSuggestions.push({key: cacheNameKey, idSegment: segment.sid})
      }
    }
    const aiSuggestionHandler = ({sid, suggestion, isCompleted, hasError}) => {
      if (!hasError && sid === segment.sid && !isCachedLastRequest) {
        setSuggestion({value: suggestion, isCompleted})
        if (isCompleted) {
          const pendingCache = [...memoSuggestions]
            .reverse()
            .find(({idSegment}) => idSegment === sid)
          memoSuggestions = memoSuggestions.map((item) => ({
            ...item,
            suggestion: item === pendingCache ? suggestion : item.suggestion,
          }))
          const message = {
            sid: segment.sid,
            segment: segment.decodedSource,
            request: requestedWord.current,
            response: suggestion,
            source: config.source_code,
            target: config.target_code,
          }
          CommonUtils.dispatchTrackingEvents('AiAssistantResponse', message)
        } else if (hasError) {
          setHasError(true)
        }
      }
    }

    SegmentStore.addListener(
      SegmentConstants.HELP_AI_ASSISTANT,
      aiAssistantHandler,
    )
    SegmentStore.addListener(
      SegmentConstants.AI_SUGGESTION,
      aiSuggestionHandler,
    )

    const lastSuggestionResult = SegmentStore.getAiSuggestion(segment.sid)
    if (
      !lastSuggestionResult &&
      SegmentStore.helpAiAssistantWords?.sid === segment.sid
    )
      aiAssistantHandler(SegmentStore.helpAiAssistantWords)

    return () => {
      SegmentStore.removeListener(
        SegmentConstants.HELP_AI_ASSISTANT,
        aiAssistantHandler,
      )
      SegmentStore.removeListener(
        SegmentConstants.AI_SUGGESTION,
        aiSuggestionHandler,
      )
    }
  }, [segment.sid, segment.updatedSource])

  const isTabOpen = active_class === 'open'

  return (
    <div
      key={`container_${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {!hasError && typeof suggestion?.value !== 'undefined' ? (
        <div>
          <div className="ai-assistant-container">
            <div className="suggestion">
              <span>Meaning in context</span>
              <span>{suggestion.value}</span>
            </div>
            <div>
              {suggestion?.isCompleted && (
                <>
                  <span>Was this suggestion useful?</span>
                  <div className="feedback-icons">
                    <span
                      className={`like${
                        feedbackLeave === 'Yes' ? ' active' : ''
                      }`}
                      onClick={() => sendFeedback(true)}
                    >
                      <IconLike />
                    </span>
                    <span
                      className={`dislike${
                        feedbackLeave === 'No' ? ' active' : ''
                      }`}
                      onClick={() => sendFeedback(false)}
                    >
                      <IconDislike />
                    </span>
                  </div>
                </>
              )}
            </div>
          </div>
          <div className="tm-matches concordances">
            <div className="tm-matches-title">
              <span>TM matches</span>
              {isLoadingTmMatches && (
                <div className="loading-container">
                  <span className="loader loader_on" />
                </div>
              )}
            </div>
            <TabConcordanceResults
              ref={resultsRef}
              segment={segment}
              isActive={isTabOpen}
            />
          </div>
        </div>
      ) : hasError ? (
        <span className="suggestion-error">
          It looks like we have encountered an error with this request. Please
          refresh the page and try again
        </span>
      ) : (
        <div className="loading-container">
          <span className="loader loader_on" />
        </div>
      )}
    </div>
  )
}

SegmentFooterTabAiAssistant.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  tab_class: PropTypes.string,
  segment: PropTypes.object,
}
