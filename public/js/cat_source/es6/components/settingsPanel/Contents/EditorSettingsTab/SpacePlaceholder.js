import React, {useContext, useState} from 'react'
import Switch from '../../../common/Switch'
import SegmentActions from '../../../../actions/SegmentActions'
import {setTagSignatureMiddleware} from '../../../segments/utils/DraftMatecatUtils/tagModel'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper'

const METADATA_KEY = 'show_whitespace'

export const SpacePlaceholder = () => {
  const {userInfo, setUserMetadataKey} = useContext(ApplicationWrapperContext)

  const [isActive, setIsActive] = useState(
    userInfo.metadata[METADATA_KEY] === 1,
  )

  const onChange = (isActive) => {
    setIsActive(isActive)

    setUserMetadataKey(METADATA_KEY, isActive ? 1 : 0)

    setTagSignatureMiddleware('space', () => isActive)
    SegmentActions.refreshTagMap()
  }
  return (
    <div className="options-box charscounter">
      <div className="option-description">
        <h3>Show whitespace characters</h3>
        <p>
          Activate this option to have whitespaces replaced with a dot in the
          source and target of segments and TM matches.
        </p>
      </div>
      <div className="options-box-value">
        <Switch
          active={isActive}
          onChange={onChange}
          testId="switch-space-counter"
        />
      </div>
    </div>
  )
}
