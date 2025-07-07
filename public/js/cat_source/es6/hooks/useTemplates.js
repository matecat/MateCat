import {useCallback, useRef, useState} from 'react'
import {isEqual} from 'lodash'
import PropTypes from 'prop-types'

const normalizeKeys = ({template, schema}) =>
  Object.entries(template).reduce((acc, cur) => {
    const currentKey = cur[0]
    const keyFromSchema = schema[cur[0]]
    return {
      ...acc,
      ...(typeof keyFromSchema === 'string'
        ? {[keyFromSchema]: cur[1]}
        : {[currentKey]: cur[1]}),
    }
  }, {})

const isSpecialProp = (prop) => prop === 'isSelected' || prop === 'isTemporary'

const isValidProp = ({target, prop, schema}) => {
  const key = isSpecialProp(prop) || !schema[prop] ? prop : schema[prop]
  if (
    !isSpecialProp(prop) &&
    !schema[prop] &&
    !Object.values(schema).some((item) => item === prop) &&
    Reflect.getOwnPropertyDescriptor(target, key)
  ) {
    return false
  }
  return true
}

const createTemplateProxy = ({template, schema}) => {
  const isCorrespondingToSchema = Object.keys(template).every((prop) =>
    isValidProp({target: template, prop, schema}),
  )

  if (isCorrespondingToSchema) {
    return new Proxy(normalizeKeys({template, schema}), {
      get(target, prop) {
        if (isValidProp({target, prop, schema})) {
          const key = isSpecialProp(prop) || !schema[prop] ? prop : schema[prop]
          return target[key]
        } else {
          throw new Error(`Invalid prop ${prop}.`)
        }
      },
    })
  } else {
    const prop = Object.keys(template).find(
      (prop) => !isValidProp({target: template, prop, schema}),
    )
    throw new Error(`Invalid prop ${prop}.`)
  }
}

function useTemplates(schema) {
  const [templates, setTemplatesState] = useState([])
  const [currentTemplate, setCurrentTemplate] = useState()

  const templatesRef = useRef()
  templatesRef.current = templates
  const prevCurrentTemplateId = useRef()

  const setTemplates = useCallback(
    (value) => {
      setTemplatesState((prevState) => {
        const result = typeof value === 'function' ? value(prevState) : value
        return result.map((template) => createTemplateProxy({template, schema}))
      })
    },
    [schema],
  )

  const modifyingCurrentTemplate = useCallback(
    (callback) => {
      if (typeof callback !== 'function')
        throw new Error('"callback" argument is not function.')

      const originalTemplate = {
        ...templatesRef.current.find(
          ({isSelected, isTemporary}) => isSelected && !isTemporary,
        ),
      }

      const alreadyExistTemporary = templatesRef.current.some(
        ({id, isTemporary}) => id === originalTemplate.id && isTemporary,
      )
      const temporaryTemplate = alreadyExistTemporary && {
        ...templatesRef.current.find(
          ({id, isTemporary}) => id === originalTemplate.id && isTemporary,
        ),
      }

      const modifiedTemplate = normalizeKeys({
        template: callback(
          temporaryTemplate
            ? temporaryTemplate
            : {...originalTemplate, isTemporary: true},
        ),
        schema,
      })

      const {isTemporary, ...comparableModifiedTemplate} = modifiedTemplate // eslint-disable-line

      // If modified template is equal to original template clean up temporary template
      if (isEqual(comparableModifiedTemplate, originalTemplate)) {
        setTemplates(
          templatesRef.current.filter(({isTemporary}) => !isTemporary),
        )
        setCurrentTemplate(
          createTemplateProxy({template: originalTemplate, schema}),
        )
        return
      }

      const modifiedTemplates = !temporaryTemplate
        ? [
            ...templatesRef.current,
            {
              ...modifiedTemplate,
              isTemporary: true,
            },
          ]
        : templatesRef.current.map((template) =>
            template.isTemporary ? modifiedTemplate : template,
          )

      setTemplates(modifiedTemplates)
      setCurrentTemplate(
        createTemplateProxy({template: modifiedTemplate, schema}),
      )
    },
    [setTemplates, schema],
  )

  const checkSpecificTemplatePropsAreModified = useCallback((props) => {
    if (!Array.isArray(props)) throw new Error('Argument props is not array.')

    const getOnlyPropsInvolved = (template) =>
      Object.entries(template)
        .filter(([key]) => props.includes(key))
        .reduce((acc, cur) => ({...acc, [cur[0]]: cur[1]}), {})

    const originalTemplate = templatesRef.current.find(
      ({isSelected, isTemporary}) => isSelected && !isTemporary,
    )
    const temporaryTemplate = templatesRef.current.find(
      ({id, isTemporary}) => id === originalTemplate.id && isTemporary,
    )

    if (!temporaryTemplate) return false
    return !isEqual(
      getOnlyPropsInvolved(originalTemplate),
      getOnlyPropsInvolved(temporaryTemplate),
    )
  }, [])

  const onChangeCurrentTemplate = (current) => {
    setTemplates((prevState) =>
      prevState.filter(({isTemporary}) => !isTemporary),
    )
    setCurrentTemplate(createTemplateProxy({template: current, schema}))
  }

  const {id: currentTemplateId} =
    templates.find(({isSelected}) => isSelected) ?? {}

  // set current template
  if (
    typeof currentTemplateId === 'number' &&
    currentTemplateId !== prevCurrentTemplateId.current
  ) {
    onChangeCurrentTemplate(templates.find(({isSelected}) => isSelected))
  }
  prevCurrentTemplateId.current = currentTemplateId

  return {
    templates,
    currentTemplate,
    setTemplates,
    modifyingCurrentTemplate,
    checkSpecificTemplatePropsAreModified,
  }
}

useTemplates.propTypes = {
  schema: PropTypes.object,
}

export default useTemplates
