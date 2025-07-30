import React, {useContext, useEffect, useRef} from 'react'
import {SUBTEMPLATE_MODIFIERS, SubTemplatesContext} from './SubTemplate'

export const SubTemplateNameInput = () => {
  const {
    templateName,
    setTemplateName,
    updateNameBehaviour,
    createTemplate,
    templateModifier,
  } = useContext(SubTemplatesContext)

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
        if (templateModifier === SUBTEMPLATE_MODIFIERS.CREATE) {
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
