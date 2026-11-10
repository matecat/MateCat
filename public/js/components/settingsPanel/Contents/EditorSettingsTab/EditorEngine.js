import React, {useContext, useState} from 'react'
import Switch from '../../../common/Switch'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import {
  EDITOR_ENGINE_KEY,
  isNewEditorActive,
} from '../../../editor/matecat/editorEngine'

export const EditorEngine = () => {
  const {userInfo, setUserMetadataKey} = useContext(ApplicationWrapperContext)

  const [isActive, setIsActive] = useState(isNewEditorActive(userInfo.metadata))

  const onChange = (isActive) => {
    setIsActive(isActive)

    setUserMetadataKey(EDITOR_ENGINE_KEY, isActive ? 'cm6' : 'draft')
  }
  return (
    <div className="options-box editor-engine">
      <div className="option-description">
        <h3>New editor</h3>
        <p>
          Use the new editor for source and target. Turn it off to go back to
          the previous editor.
        </p>
      </div>
      <div className="options-box-value">
        <Switch
          active={isActive}
          onChange={onChange}
          testId="switch-editor-engine"
        />
      </div>
    </div>
  )
}
