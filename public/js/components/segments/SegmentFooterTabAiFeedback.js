import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'

export const SegmentFooterTabAiFeedback = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [feedback, setFeedback] = useState()

  useEffect(() => {
    const requestFeedback = () => {
      setFeedback({
        content:
          'The translation accurately reflects all elements: the comparison with Venice, the historical reference, and the list of qualities. “Capitale olandese del XVII secolo” is a precise rendering of “17th century capital city of Holland.” Alternatives like “straordinari spazi verdi” (extraordinary green spaces) were possible, yet “meravigliosi” fits the tone naturally.',
      })
    }

    SegmentStore.addListener(SegmentConstants.AI_FEEDBACK, requestFeedback)

    return () =>
      SegmentStore.removeListener(SegmentConstants.AI_FEEDBACK, requestFeedback)
  }, [segment])

  return (
    <div
      key={`container_${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {feedback?.content ? (
        <div className="ai-feature-content">
          <h4>Score:</h4>
          <p>{feedback.content}</p>
        </div>
      ) : feedback?.error ? (
        <div className="ai-feature-content">
          <p>{feedback.error}</p>
        </div>
      ) : (
        <div className="loading-container">
          <span className="loader loader_on" />
        </div>
      )}
    </div>
  )
}

SegmentFooterTabAiFeedback.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  tab_class: PropTypes.string,
  segment: PropTypes.object,
}
