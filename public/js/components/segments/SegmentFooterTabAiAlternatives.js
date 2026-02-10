import React, {useEffect, useState} from 'react'
import PropTypes from 'prop-types'
import SegmentStore from '../../stores/SegmentStore'
import SegmentConstants from '../../constants/SegmentConstants'
import {Button, BUTTON_MODE} from '../common/Button/Button'
import SwitchHorizontal from '../../../img/icons/SwitchHorizontal'

export const SegmentFooterTabAiAlternatives = ({
  code,
  active_class,
  tab_class,
  segment,
}) => {
  const [alternatives, setAlternatives] = useState()

  useEffect(() => {
    const requestAlternatives = ({sid, text}) => {
      setAlternatives()
      console.log(text)
      const currentSegment = segment
    }

    SegmentStore.addListener(
      SegmentConstants.AI_ALTERNATIVES,
      requestAlternatives,
    )

    return () =>
      SegmentStore.removeListener(
        SegmentConstants.AI_ALTERNATIVES,
        requestAlternatives,
      )
  }, [segment])

  const copyAlternative = () => false

  const allowHTML = (string) => {
    return {__html: string}
  }

  return (
    <div
      key={`container_${code}`}
      className={`tab sub-editor ${active_class} ${tab_class}`}
      id={`segment-${segment.sid}-${tab_class}`}
    >
      {alternatives?.length ? (
        <div className="ai-feature-content">
          <div className="ai-feature-options">
            {alternatives.map((alternative) => (
              <div key={style.id}>
                <div>
                  <h4>{alternative} </h4>
                  <p dangerouslySetInnerHTML={allowHTML(alternative)}></p>
                </div>
                <Button
                  className="ai-feature-button"
                  mode={BUTTON_MODE.OUTLINE}
                  onClick={() => copyAlternative()}
                >
                  <SwitchHorizontal size={16} />
                </Button>
              </div>
            ))}
          </div>
        </div>
      ) : alternatives?.error ? (
        <div className="ai-feature-content">
          <p>{alternatives.error}</p>
        </div>
      ) : (
        <div className="loading-container">
          <span className="loader loader_on" />
        </div>
      )}
    </div>
  )
}

SegmentFooterTabAiAlternatives.propTypes = {
  code: PropTypes.string,
  active_class: PropTypes.string,
  tab_class: PropTypes.string,
  segment: PropTypes.object,
}
