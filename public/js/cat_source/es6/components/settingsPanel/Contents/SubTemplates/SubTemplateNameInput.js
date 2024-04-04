import React, {useContext, useEffect, useRef} from 'react'
import {SubTemplatesContext} from './SubTemplate'

export const SubTemplateNameInput = () => {
  const {templateName, setTemplateName, updateNameBehaviour} =
    useContext(SubTemplatesContext)

  const container = useRef()

  const onChangeTemplateName = (e) => setTemplateName(e.currentTarget.value)

  useEffect(() => {
    const {current} = container

    const updateName = () => updateNameBehaviour.current.confirm()

    const cancel = () => updateNameBehaviour.current.cancel()

    const handleKeyDown = (e) => {
      if (e.key === 'Enter') {
        e.preventDefault()
        updateName()
      } else if (e.key === 'Escape') {
        e.preventDefault()
        e.stopPropagation()
        cancel()
      }
    }

    current.addEventListener('keydown', handleKeyDown, true)

    return () => current.removeEventListener('keydown', handleKeyDown)
  }, [updateNameBehaviour])

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
