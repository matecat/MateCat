import React, {useState} from 'react'
import Switch from '../../../common/Switch'
import SegmentActions from '../../../../actions/SegmentActions'
import {setTagSignatureMiddleware} from '../../../segments/utils/DraftMatecatUtils/tagModel'

export const SPACE_PLACEHOLDER_STORAGE_KEY = 'spacePlaceholder'

// check space placeholder is active on init
setTagSignatureMiddleware(
  'space',
  () => window.localStorage.getItem(SPACE_PLACEHOLDER_STORAGE_KEY) === 'true',
)

export const SpacePlaceholder = () => {
  const [active, setActive] = useState(
    window.localStorage.getItem(SPACE_PLACEHOLDER_STORAGE_KEY) === 'true',
  )
  const onChange = (active) => {
    setActive(active)
    window.localStorage.setItem(
      SPACE_PLACEHOLDER_STORAGE_KEY,
      active.toString(),
    )
    setTagSignatureMiddleware('space', () => active)
    SegmentActions.refreshTagMap()
  }
  return (
    <div className="options-box charscounter">
      <div className="option-description">
        <h3>Space placeholder</h3>
        <p>bla bla</p>
      </div>
      <div className="options-box-value">
        <Switch
          active={active}
          onChange={onChange}
          testId="switch-chars-counter"
        />
      </div>
    </div>
  )
}
