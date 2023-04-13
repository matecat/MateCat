import React, {useEffect, useState, useRef} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import IconLike from '../icons/IconLike'
import IconDislike from '../icons/IconDislike'
import {aiSuggestion} from '../../api/aiSuggestion/aiSuggestion'
import TagUtils from '../../utils/tagUtils'
import CommonUtils from '../../utils/commonUtils'

export const SegmentFooterTabAiAssistant = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [suggestion, setSuggestion] = useState()
  const [hasError, setHasError] = useState(false)
  const [feedbackLeave, setFeedbackLeave] = useState()

  let requestedWord = useRef()

  const sendFeedback = (feedback) => {
    const message = {
      sid: segment.sid,
      segment: segment.decodedSource,
      request: requestedWord.current,
      response: suggestion,
      source: config.source_code,
      target: config.target_code,
      feedback: feedback ? 'Yes' : 'No',
    }
    CommonUtils.dispatchTrackingEvents('AiAssistantFeedback', message)
    setFeedbackLeave(feedback ? 'Yes' : 'No')
  }

  useEffect(() => {
    const aiAssistantHandler = ({sid, value}) => {
      if (sid === segment.sid) {
        setSuggestion(undefined)
        setHasError(false)
        setFeedbackLeave(undefined)
        requestedWord.current = value

        const sourceContent = TagUtils.prepareTextToSend(segment.updatedSource)
        aiSuggestion({
          idSegment: segment.sid,
          words: value,
          phrase: sourceContent,
        })
      }
    }
    const aiSuggestionHandler = ({sid, suggestion, hasError}) => {
      if (!hasError && sid === segment.sid) setSuggestion(suggestion)
      else if (hasError) setHasError(true)
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

  return (
    <div
      key={`container_${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {!hasError && typeof suggestion !== 'undefined' ? (
        <div className="ai-assistant-container">
          <div>{suggestion}</div>
          <div>
            <span>Was this suggestion useful?</span>
            <div className="feedback-icons">
              <span
                className={`like${feedbackLeave === 'Yes' ? ' active' : ''}`}
                onClick={() => sendFeedback(true)}
              >
                <IconLike />
              </span>
              <span
                className={`dislike${feedbackLeave === 'No' ? ' active' : ''}`}
                onClick={() => sendFeedback(false)}
              >
                <IconDislike />
              </span>
            </div>
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
