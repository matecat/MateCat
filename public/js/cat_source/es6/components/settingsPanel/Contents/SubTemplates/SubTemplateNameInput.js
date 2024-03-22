import React, {useContext} from 'react'
import {SubTemplatesContext} from './SubTemplate'

export const SubTemplateNameInput = () => {
  const {templateName, setTemplateName} = useContext(SubTemplatesContext)

  const onChangeTemplateName = (e) => setTemplateName(e.currentTarget.value)

  return (
    <input
      className="template-name"
      data-testid="template-name-input"
      value={templateName}
      onChange={onChangeTemplateName}
      autoFocus
    ></input>
  )
}
