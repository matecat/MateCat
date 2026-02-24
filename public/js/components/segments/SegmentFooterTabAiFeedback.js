import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {aiFeedback} from '../../api/aiFeedback/aiFeedback'
import CatToolStore from '../../stores/CatToolStore'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {Badge, BADGE_TYPE} from '../common/Badge/Badge'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'

export const SegmentFooterTabAiFeedback = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [feedback, setFeedback] = useState()

  useEffect(() => {
    const requestFeedback = () => {
      setFeedback()

      const decodedSource = DraftMatecatUtils.transformTagsToText(
        DraftMatecatUtils.excludeSomeTagsFromText(segment.segment, [
          'g',
          'bx',
          'ex',
          'x',
        ]),
      ).replace(/·/g, ' ')
      const decodedTarget = DraftMatecatUtils.transformTagsToText(
        DraftMatecatUtils.excludeSomeTagsFromText(segment.translation, [
          'g',
          'bx',
          'ex',
          'x',
        ]),
      ).replace(/·/g, ' ')

      aiFeedback({
        idSegment: segment.sid,
        source: decodedSource,
        target: decodedTarget,
        style: CatToolStore.getJobMetadata().project.mt_extra.lara_style,
      })
    }

    const receiveFeedback = ({data}) => {
      if (!data.has_error && data.message?.comment) {
        setFeedback({
          category: data.message.category,
          content: data.message.comment,
        })
      } else {
        setFeedback({
          error:
            typeof data.message === 'string' && data.message !== ''
              ? data.message
              : 'Service currently unavailable. Please try again in a moment.',
          retryCallback: () => requestFeedback(),
        })
      }
    }

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

  const getBadgeType = (category) => {
    let _type = BADGE_TYPE.GREEN

    switch (category) {
      case 'Could Be Improved':
        _type = BADGE_TYPE.YELLOW
        break
      case 'Does Not Match Source':
        _type = BADGE_TYPE.RED
        break
    }

    return _type
  }

  return (
    <div
      key={`container_${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {feedback?.content ? (
        <div className="ai-feature-content">
          <h4>
            Score:{' '}
            <Badge type={getBadgeType(feedback.category)}>
              {feedback.category}
            </Badge>
          </h4>
          <p>{feedback.content}</p>
        </div>
      ) : feedback?.error ? (
        <div className="ai-feature-content">
          <p>{feedback.error}</p>
          <Button
            className="ai-feature-button-retry"
            type={BUTTON_TYPE.DEFAULT}
            mode={BUTTON_MODE.OUTLINE}
            onClick={feedback.retryCallback}
          >
            Retry
          </Button>
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
