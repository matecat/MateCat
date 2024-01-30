import {useCallback, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {isEqual} from 'lodash'
import {getProjectTemplates} from '../api/getProjectTemplates/getProjectTemplates'
import TEXT_UTILS from '../utils/textUtils'

export const isStandardTemplate = ({id} = {}) => id === 0

function useProjectTemplates(canRetrieveTemplates) {
  const [projectTemplates, setProjectTemplates] = useState([])
  const [currentProjectTemplate, setCurrentProjectTemplate] = useState()
  const [availableTemplateProps, setAvailableTemplateProps] = useState({})

  const projectTemplatesRef = useRef()
  projectTemplatesRef.current = projectTemplates
  const prevCurrentProjectTemplateId = useRef()

  const modifyingCurrentTemplate = useCallback((callback) => {
    if (typeof callback !== 'function')
      throw new Error('"callback" argument is not function.')

    const originalTemplate = projectTemplatesRef.current.find(
      ({isSelected, isTemporary}) => isSelected && !isTemporary,
    )
    const temporaryTemplate = projectTemplatesRef.current.find(
      ({id, isTemporary}) => id === originalTemplate.id && isTemporary,
    )

    const modifiedTemplate = callback(
      temporaryTemplate
        ? temporaryTemplate
        : {...originalTemplate, isTemporary: true},
    )
    const {isTemporary, ...comparableModifiedTemplate} = modifiedTemplate // eslint-disable-line

    const originalTemplateKeys = Object.keys(originalTemplate).filter(
      (value) => value !== 'isSelected',
    )
    const modifiedTemplateKeys = Object.keys(comparableModifiedTemplate).filter(
      (value) => value !== 'isSelected',
    )

    if (!isEqual(modifiedTemplateKeys, originalTemplateKeys))
      throw new Error('Error template schema not valid.')

    // If modified template is equal to original template clean up temporary template
    if (isEqual(comparableModifiedTemplate, originalTemplate)) {
      setProjectTemplates(
        projectTemplatesRef.current.filter(({isTemporary}) => !isTemporary),
      )
      setCurrentProjectTemplate(originalTemplate)
      return
    }

    const modifiedProjectTemplates = !temporaryTemplate
      ? [
          ...projectTemplatesRef.current,
          {
            ...modifiedTemplate,
            isTemporary: true,
          },
        ]
      : projectTemplatesRef.current.map((template) =>
          template.isTemporary ? modifiedTemplate : template,
        )

    setProjectTemplates(modifiedProjectTemplates)
    setCurrentProjectTemplate(modifiedTemplate)
  }, [])

  const checkSpecificTemplatePropsAreModified = useCallback((props) => {
    if (!Array.isArray(props)) throw new Error('Argument props is not array.')

    const getOnlyPropsInvolved = (template) =>
      Object.entries(template)
        .filter(([key]) => props.includes(key))
        .reduce((acc, cur) => ({...acc, [cur[0]]: cur[1]}), {})

    const originalTemplate = projectTemplatesRef.current.find(
      ({isSelected, isTemporary}) => isSelected && !isTemporary,
    )
    const temporaryTemplate = projectTemplatesRef.current.find(
      ({id, isTemporary}) => id === originalTemplate.id && isTemporary,
    )

    if (!temporaryTemplate) return false
    return !isEqual(
      getOnlyPropsInvolved(originalTemplate),
      getOnlyPropsInvolved(temporaryTemplate),
    )
  }, [])

  // retrieve templates
  useEffect(() => {
    if (!canRetrieveTemplates) return

    let cleanup = false

    getProjectTemplates().then(({items}) => {
      if (!cleanup) {
        setProjectTemplates(
          items.map((template) => ({
            ...template,
            isSelected: isStandardTemplate(template),
          })),
        )

        const propKeys = Object.keys(
          items.find((template) => isStandardTemplate(template)),
        ).filter((value) => value !== 'id' && value !== 'name')
        setAvailableTemplateProps(
          propKeys.reduce(
            (acc, cur) => ({...acc, [TEXT_UTILS.stripUnderscore(cur)]: cur}),
            {},
          ),
        )
      }
    })

    return () => (cleanup = true)
  }, [canRetrieveTemplates])

  const onChangeCurrentProjectTemplate = (current) => {
    setProjectTemplates((prevState) =>
      prevState.filter(({isTemporary}) => !isTemporary),
    )
    setCurrentProjectTemplate(current)
  }

  const {id: currentProjectTemplateId} =
    projectTemplates.find(({isSelected}) => isSelected) ?? {}

  // set current project template
  if (
    typeof currentProjectTemplateId === 'number' &&
    currentProjectTemplateId !== prevCurrentProjectTemplateId.current
  ) {
    onChangeCurrentProjectTemplate(
      projectTemplates.find(({isSelected}) => isSelected),
    )
  }
  prevCurrentProjectTemplateId.current = currentProjectTemplateId

  return {
    projectTemplates,
    currentProjectTemplate,
    availableTemplateProps,
    setProjectTemplates,
    modifyingCurrentTemplate,
    checkSpecificTemplatePropsAreModified,
  }
}

useProjectTemplates.propTypes = {
  canRetrieveTemplates: PropTypes.bool,
}

export default useProjectTemplates
