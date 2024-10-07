import React, {useContext, useState} from 'react'
import Switch from '../../../common/Switch'
import SegmentActions from '../../../../actions/SegmentActions'
import {setTagSignatureMiddleware} from '../../../segments/utils/DraftMatecatUtils/tagModel'
import {SPACE_PLACEHOLDER_STORAGE_KEY} from '../../../../constants/Constants'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper'
import {updateUserMetadata} from '../../../../api/updateUserMetadata/updateUserMetadata'

const METADATA_KEY = 'show_whitespace'

export const SpacePlaceholder = () => {
  const {userInfo, setUserInfo} = useContext(ApplicationWrapperContext)

  const [isActive, setIsActive] = useState(
    userInfo.metadata[METADATA_KEY] === true,
  )

  const onChange = (isActive) => {
    setIsActive(isActive)

    updateUserMetadata({key: METADATA_KEY, value: isActive ? 1 : 0}).then(
      ({value}) => {
        setUserInfo((prevState) => ({
          ...prevState,
          metadata: {...prevState.metadata, [METADATA_KEY]: value},
        }))
      },
    )

    window.localStorage.setItem(
      SPACE_PLACEHOLDER_STORAGE_KEY,
      isActive.toString(),
    )
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
