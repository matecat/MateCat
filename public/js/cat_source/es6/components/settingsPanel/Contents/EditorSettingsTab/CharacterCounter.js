import React, {useContext, useState} from 'react'
import Switch from '../../../common/Switch'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper'

const METADATA_KEY = 'character_counter'

export const CharacterCounter = () => {
  const {userInfo, setUserMetadataKey} = useContext(ApplicationWrapperContext)

  const [isActive, setIsActive] = useState(
    userInfo.metadata[METADATA_KEY] === 1,
  )

  const onChange = (isActive) => {
    setUserMetadataKey(METADATA_KEY, isActive ? 1 : 0)

    setIsActive(isActive)
  }
  return (
    <div className="options-box charscounter">
      <div className="option-description">
        <h3>Character counter</h3>
        <p>
          Enabling this option makes a counter appear that counts the number of
          characters in the target section of each segment.
        </p>
      </div>
      <div className="options-box-value">
        <Switch
          active={isActive}
          onChange={onChange}
          testId="switch-chars-counter"
        />
      </div>
    </div>
  )
}
