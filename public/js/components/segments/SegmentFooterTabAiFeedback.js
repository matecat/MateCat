import React, {useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {aiFeedback} from '../../api/aiFeedback/aiFeedback'
import CatToolStore from '../../stores/CatToolStore'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {Badge, BADGE_TYPE} from '../common/Badge/Badge'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'
import {decodeTagsToUnicodeChar} from './utils/DraftMatecatUtils/tagUtils'
import {LARA_STYLES} from '../settingsPanel/Contents/MachineTranslationTab/LaraOptions'
import CommonUtils from '../../utils/commonUtils'
import IconLike from '../icons/IconLike'
import IconDislike from '../icons/IconDislike'
import {MemoizeRequest} from '../../utils/MemoizeRequest'

const aiCache = new MemoizeRequest()

export const SegmentFooterTabAiFeedback = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [feedback, setFeedback] = useState()
  const [feedbackLeave, setFeedbackLeave] = useState()

  const requestingParams = useRef()

  useEffect(() => {
    const requestFeedback = () => {
      if (requestingParams.current) return

      setFeedback()
      setFeedbackLeave(undefined)

      const decodedSource = DraftMatecatUtils.transformTagsToText(
        DraftMatecatUtils.excludeSomeTagsFromText(
          decodeTagsToUnicodeChar(segment.segment),
          ['g', 'bx', 'ex', 'x'],
        ),
      ).replace(/·/g, ' ')
      const decodedTarget = DraftMatecatUtils.transformTagsToText(
        DraftMatecatUtils.excludeSomeTagsFromText(
          decodeTagsToUnicodeChar(segment.translation),
          ['g', 'bx', 'ex', 'x'],
        ),
      ).replace(/·/g, ' ')

      requestingParams.current = {
        idSegment: segment.sid,
        source: decodedSource,
        target: decodedTarget,
        style:
          CatToolStore.getJobMetadata().project.mt_extra.lara_style ??
          LARA_STYLES.FAITHFUL,
      }

      const cached = aiCache.get(requestingParams.current)

      if (cached) {
        receiveFeedback({data: cached})
      } else {
        aiFeedback(requestingParams.current).catch(() =>
          receiveFeedback({data: {has_error: true, message: 'Fetch failed'}}),
        )
      }
    }

    const receiveFeedback = ({data}) => {
      if (!data.has_error && data.message?.comment) {
        aiCache.set(requestingParams.current, data)

        setFeedback({
          category: data.message.category,
          content: data.message.comment,
        })
      } else {
        setFeedback({
          error: 'Something went wrong. Please try again in a moment.',
          retryCallback: () => requestFeedback(),
        })
        //Track Event
        const message = {
          sid: segment.sid,
          segment: segment.decodedSource,
          source: config.source_code,
          target: config.target_code,
          error: data.message,
        }
        CommonUtils.dispatchTrackingEvents('AiLaraFeedbackError', message)
      }

      requestingParams.current = undefined
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
  const sendFeedback = (feedback) => {
    const message = {
      sid: segment.sid,
      segment: segment.decodedSource,
      source: config.source_code,
      target: config.target_code,
      feedback: feedback ? 'Yes' : 'No',
    }
    CommonUtils.dispatchTrackingEvents('AiLaraFeedbackUserFeedback', message)
    setFeedbackLeave(feedback ? 'Yes' : 'No')
  }
  const feedbackContent =
    typeof feedbackLeave === 'undefined' ? (
      <>
        <span className="feedback-paragraph">
          <b>Submit your feedback</b>
          <br />
          Was this suggestion useful?
        </span>
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
      </>
    ) : (
      <>
        <div className="feedback-icons">
          <span className="submited">
            {feedbackLeave === 'Yes' ? <IconLike /> : <IconDislike />}
          </span>
        </div>
        <span className="feedback-paragraph">
          <b>Thank you!</b>
          <br />
          We really appreciate your feedback.
        </span>
      </>
    )

  return (
    <div
      key={`container_${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {feedback?.content ? (
        <div className="ai-feature-content">
          <div className="content">
            <h4>
              Score:{' '}
              <Badge type={getBadgeType(feedback.category)}>
                {feedback.category}
              </Badge>
            </h4>
            <p>{feedback.content}</p>
          </div>
          <div
            className={`feedback-container${
              typeof feedbackLeave !== 'undefined'
                ? ' feedback-container-submited'
                : ''
            }`}
          >
            {feedbackContent}
          </div>
        </div>
      ) : feedback?.error ? (
        <div className="ai-feature-content">
          <div className="content">
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
