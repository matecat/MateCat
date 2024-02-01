import React, {useContext} from 'react'
import {ProjectTemplateContext} from './ProjectTemplateContext'

export const TemplateNameInput = () => {
  const {templateName, setTemplateName} = useContext(ProjectTemplateContext)

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
