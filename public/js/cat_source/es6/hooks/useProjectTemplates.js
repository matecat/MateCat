import {useCallback, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {isEqual} from 'lodash'
import {getProjectTemplates} from '../api/getProjectTemplates/getProjectTemplates'

export const isStandardTemplate = ({id} = {}) => id === 0
export const SCHEMA_KEYS = {
  id: 'id',
  uid: 'uid',
  isDefault: 'is_default',
  createdAt: 'created_at',
  modifiedAt: 'modified_at',
  name: 'name',
  idTeam: 'id_team',
  speech2text: 'speech2text',
  lexica: 'lexica',
  tagProjection: 'tag_projection',
  crossLanguageMatches: 'cross_language_matches',
  segmentationRule: 'segmentation_rule',
  mt: 'mt',
  tm: 'tm',
  payableRateTemplateId: 'payable_rate_template_id',
  qaModelTemplateId: 'qa_model_template_id',
  getPublicMatches: 'get_public_matches',
  pretranslate100: 'pretranslate_100',
}

const normalizeKeys = (template) =>
  Object.entries(template).reduce((acc, cur) => {
    const currentKey = cur[0]
    const keyFromSchema = SCHEMA_KEYS[cur[0]]
    return {
      ...acc,
      ...(typeof keyFromSchema === 'string'
        ? {[keyFromSchema]: cur[1]}
        : {[currentKey]: cur[1]}),
    }
  }, {})

const createTemplateProxy = (template) =>
  new Proxy(normalizeKeys(template), {
    get(target, prop) {
      const isSpecialProp = prop === 'isSelected' || prop === 'isTemporary'
      const key = isSpecialProp || !SCHEMA_KEYS[prop] ? prop : SCHEMA_KEYS[prop]

      if (
        !isSpecialProp &&
        !SCHEMA_KEYS[prop] &&
        !Object.values(SCHEMA_KEYS).some((item) => item === prop)
      )
        throw new Error(`Invalid prop ${prop}.`)

      return target[key]
    },
  })

function useProjectTemplates(canRetrieveTemplates) {
  const [projectTemplates, setProjectTemplatesState] = useState([])
  const [currentProjectTemplate, setCurrentProjectTemplate] = useState()

  const projectTemplatesRef = useRef()
  projectTemplatesRef.current = projectTemplates
  const prevCurrentProjectTemplateId = useRef()

  const setProjectTemplates = useCallback((value) => {
    setProjectTemplatesState((prevState) => {
      const result = typeof value === 'function' ? value(prevState) : value
      return result.map((template) => createTemplateProxy(template))
    })
  }, [])

  const modifyingCurrentTemplate = useCallback(
    (callback) => {
      if (typeof callback !== 'function')
        throw new Error('"callback" argument is not function.')

      const originalTemplate = {
        ...projectTemplatesRef.current.find(
          ({isSelected, isTemporary}) => isSelected && !isTemporary,
        ),
      }

      const alreadyExistTemporary = projectTemplatesRef.current.some(
        ({id, isTemporary}) => id === originalTemplate.id && isTemporary,
      )
      const temporaryTemplate = alreadyExistTemporary && {
        ...projectTemplatesRef.current.find(
          ({id, isTemporary}) => id === originalTemplate.id && isTemporary,
        ),
      }

      const modifiedTemplate = normalizeKeys(
        callback(
          temporaryTemplate
            ? temporaryTemplate
            : {...originalTemplate, isTemporary: true},
        ),
      )

      const {isTemporary, ...comparableModifiedTemplate} = modifiedTemplate // eslint-disable-line

      const originalTemplateKeys = Object.keys(originalTemplate).filter(
        (value) => value !== 'isSelected',
      )
      const modifiedTemplateKeys = Object.keys(
        comparableModifiedTemplate,
      ).filter((value) => value !== 'isSelected')

      if (!isEqual(modifiedTemplateKeys, originalTemplateKeys))
        throw new Error('Error template schema not valid.')

      // If modified template is equal to original template clean up temporary template
      if (isEqual(comparableModifiedTemplate, originalTemplate)) {
        setProjectTemplates(
          projectTemplatesRef.current.filter(({isTemporary}) => !isTemporary),
        )
        setCurrentProjectTemplate(createTemplateProxy(originalTemplate))
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
      setCurrentProjectTemplate(createTemplateProxy(modifiedTemplate))
    },
    [setProjectTemplates],
  )

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
            isSelected: template.is_default,
          })),
        )
      }
    })

    return () => (cleanup = true)
  }, [canRetrieveTemplates, setProjectTemplates])

  const onChangeCurrentProjectTemplate = (current) => {
    setProjectTemplates((prevState) =>
      prevState.filter(({isTemporary}) => !isTemporary),
    )
    setCurrentProjectTemplate(createTemplateProxy(current))
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
    setProjectTemplates,
    modifyingCurrentTemplate,
    checkSpecificTemplatePropsAreModified,
  }
}

useProjectTemplates.propTypes = {
  canRetrieveTemplates: PropTypes.bool,
}

export default useProjectTemplates
