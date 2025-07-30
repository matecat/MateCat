import React, {useContext, useEffect, useRef} from 'react'
import {ProjectTemplateContext} from './ProjectTemplateContext'
import {TEMPLATE_MODIFIERS} from './ProjectTemplate'

export const TemplateNameInput = () => {
  const {
    templateName,
    setTemplateName,
    updateNameBehaviour,
    createTemplate,
    templateModifier,
  } = useContext(ProjectTemplateContext)
  const container = useRef()

  const onChangeTemplateName = (e) => setTemplateName(e.currentTarget.value)

  useEffect(() => {
    const {current} = container

    const create = () => createTemplate.current()
    const updateName = () => updateNameBehaviour.current.confirm()
    const cancel = () => updateNameBehaviour.current.cancel()

    const handleKeyDown = (e) => {
      if (e.key === 'Enter') {
        e.preventDefault()
        if (templateModifier === TEMPLATE_MODIFIERS.CREATE) {
          create()
        } else {
          updateName()
        }
      } else if (e.key === 'Escape') {
        e.preventDefault()
        e.stopPropagation()
        cancel()
      }
    }
    if (templateName) current.addEventListener('keydown', handleKeyDown)

    return () => current.removeEventListener('keydown', handleKeyDown)
  }, [updateNameBehaviour, createTemplate, templateModifier, templateName])
  return (
    <input
      ref={container}
      className="template-name"
      data-testid="template-name-input"
      value={templateName}
      onChange={onChangeTemplateName}
      autoFocus
    ></input>
  )
}
