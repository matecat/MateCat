import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {aiFeedback} from '../../api/aiFeedback/aiFeedback'
import CatToolStore from '../../stores/CatToolStore'
import DraftMatecatUtils from './utils/DraftMatecatUtils'

export const SegmentFooterTabAiFeedback = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [feedback, setFeedback] = useState()

  useEffect(() => {
    const requestFeedback = () => {
      // setFeedback({
      //   content:
      //     'The translation accurately reflects all elements: the comparison with Venice, the historical reference, and the list of qualities. “Capitale olandese del XVII secolo” is a precise rendering of “17th century capital city of Holland.” Alternatives like “straordinari spazi verdi” (extraordinary green spaces) were possible, yet “meravigliosi” fits the tone naturally.',
      // })

      const decodedSource = DraftMatecatUtils.excludeSomeTagsFromText(
        segment.segment,
        ['g', 'bx', 'ex', 'x', 'ph'],
      )
      const decodedTarget = DraftMatecatUtils.excludeSomeTagsFromText(
        segment.translation,
        ['g', 'bx', 'ex', 'x', 'ph'],
      )

      aiFeedback({
        idSegment: segment.sid,
        source: decodedSource,
        target: decodedTarget,
        style: CatToolStore.getJobMetadata().project.mt_extra.lara_style,
      })
    }

    const receiveFeedback = (data) => console.log(data)

    SegmentStore.addListener(SegmentConstants.AI_FEEDBACK, requestFeedback)
    SegmentStore.addListener(
      SegmentConstants.AI_FEEDBACK_SUGGESTION,
      receiveFeedback,
    )

    return () => {
      SegmentStore.removeListener(SegmentConstants.AI_FEEDBACK, requestFeedback)
      SegmentStore.removeListener(
        SegmentConstants.AI_FEEDBACK_SUGGESTION,
        receiveFeedback,
      )
    }
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
