import React, {useEffect, useState, useRef} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import IconLike from '../icons/IconLike'
import IconDislike from '../icons/IconDislike'
import {aiAssistantSuggestion} from '../../api/aiAssistantSuggestion/aiAssistantSuggestion'
import CommonUtils from '../../utils/commonUtils'

export const SegmentFooterTabAiAssistant = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [suggestion, setSuggestion] = useState()
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
  }

  useEffect(() => {
    const aiAssistantHandler = ({value}) => {
      setSuggestion(undefined)
      requestedWord.current = value
      aiAssistantSuggestion({phrase: value}).then((suggestion) =>
        setSuggestion(suggestion.phrase),
      )
    }

    SegmentStore.addListener(
      SegmentConstants.HELP_AI_ASSISTANT,
      aiAssistantHandler,
    )

    aiAssistantHandler(SegmentStore.helpAiAssistantParams)

    return () =>
      SegmentStore.removeListener(
        SegmentConstants.HELP_AI_ASSISTANT,
        aiAssistantHandler,
      )
  }, [])

  return (
    <div
      key={`container_${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {suggestion ? (
        <div className="ai-assistant-container">
          <div>{suggestion}</div>
          <div>
            <span>Was this suggestion useful?</span>
            <div className="feedback-icons">
              <span className="like" onClick={() => sendFeedback(false)}>
                <IconLike />
              </span>
              <span className="dislike" onClick={() => sendFeedback(false)}>
                <IconDislike />
              </span>
            </div>
          </div>
        </div>
      ) : (
        <div className="loading-container">
          <span>Loading</span>
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
