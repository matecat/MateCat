import {useCallback, useEffect, useRef, useState} from 'react'
import PropTypes from 'prop-types'
import {isEqual} from 'lodash'

const getProjectTemplates = async () =>
  new Promise((resolve) => {
    resolve([
      {
        id: 34,
        name: 'default template',
        uid: 54,
        is_default: true,
        id_team: 45,
        qa_template_id: 4456,
        payable_rate_template_id: 434,
        speech2text: true,
        lexica: true,
        tag_projection: true,
        cross_language_matches: ['it-IT', 'fr-FR'],
        segmentation_rule: 'General',
        mt: {
          id: 9,
          extra: {},
        },
        tm: [],
        get_public_matches: true,
        pretranslate_100: false,
      },
      {
        id: 3,
        name: 'template name',
        id_team: 45,
        qa_template_id: 4456,
        payable_rate_template_id: 434,
        speech2text: true,
        lexica: true,
        tag_projection: true,
        cross_language_matches: [],
        segmentation_rule: 'General',
        mt: {
          id: 10,
          extra: {
            deepl_formality: 'prefer_less',
          },
        },
        tm: [
          {
            glos: true,
            is_shared: false,
            key: '74b6c82408a028b6f020',
            name: 'abc',
            owner: true,
            tm: true,
            r: true,
            w: false,
          },
          {
            glos: true,
            is_shared: false,
            key: '21df10c8cce1b31f2d0d',
            name: 'myKey',
            owner: true,
            tm: true,
            r: true,
            w: true,
          },
        ],
        get_public_matches: false,
        pretranslate_100: true,
      },
    ])
  })

function useProjectTemplates({tmKeys, setTmKeys}) {
  const canRetrieveTemplates = !!tmKeys?.length

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
      temporaryTemplate ? temporaryTemplate : originalTemplate,
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

  const checkOneOfPropsAreModified = useCallback((props) => {
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

    const stripUnderscore = (value) =>
      value.replace(/_[^_]/g, (match) => match[1].toUpperCase())

    getProjectTemplates().then((items) => {
      if (!cleanup) {
        setProjectTemplates(
          items.map((template) => ({
            ...template,
            isSelected: template.is_default,
          })),
        )

        const propKeys = Object.keys(
          items.find(({is_default}) => is_default),
        ).filter((value) => value !== 'id' && value !== 'name')
        setAvailableTemplateProps(
          propKeys.reduce(
            (acc, cur) => ({...acc, [stripUnderscore(cur)]: cur}),
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

    // Sync dependencies state with current project template
    const {tm} = current

    setTmKeys((prevState) =>
      prevState.map((tmItem) => {
        const tmFromTemplate = tm.find(({key}) => key === tmItem.key)
        return {
          ...tmItem,
          r: false,
          w: false,
          isActive: false,
          ...(tmFromTemplate && {...tmFromTemplate, isActive: true}),
        }
      }),
    )
  }

  const currentProjectTemplateId = projectTemplates.find(
    ({isSelected}) => isSelected,
  )?.id
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
    checkOneOfPropsAreModified,
  }
}

useProjectTemplates.propTypes = {
  tmKeys: PropTypes.array.isRequired,
  setTmKeys: PropTypes.func.isRequired,
}

export default useProjectTemplates
