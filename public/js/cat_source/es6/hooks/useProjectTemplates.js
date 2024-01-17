import {useCallback, useEffect, useRef, useState} from 'react'

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
        mt: {},
        tm: [],
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
          id: 123,
          extra: {
            glossaries: [1, 2, 4],
            formality: 'low',
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
        ],
      },
    ])
  })

function useProjectTemplates() {
  const [projectTemplates, setProjectTemplates] = useState([])
  const [currentProjectTemplate, setCurrentProjectTemplate] = useState()

  const prevCurrentProjectTemplateId = useRef()

  const modifyingCurrentTemplate = useCallback(
    (callback) => {
      if (typeof callback !== 'function')
        throw new Error('"callback" argument is not function.')

      const selectedTemplate = projectTemplates.find(
        ({isSelected}) => isSelected,
      )
      const temporaryTemplate = projectTemplates.find(
        ({id, isTemporary}) => id === selectedTemplate.id && isTemporary,
      )

      const modifiedTemplate = callback(
        temporaryTemplate ? temporaryTemplate : selectedTemplate,
      )

      if (!temporaryTemplate) {
        // create temporary template
        setProjectTemplates((prevState) => [
          ...prevState,
          {
            ...modifiedTemplate,
            isTemporary: true,
          },
        ])
      } else {
        // modify temporary template
        setProjectTemplates((prevState) =>
          prevState.map((template) =>
            template.isTemporary ? modifiedTemplate : template,
          ),
        )
      }
    },
    [projectTemplates],
  )

  // retrieve templates
  useEffect(() => {
    let cleanup = false

    getProjectTemplates().then((items) => {
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
  }, [])

  const getCurrentTemplate = () => {
    const currentTemplate = projectTemplates.find(({isSelected}) => isSelected)
    const temporaryCurrentTemplate =
      projectTemplates.find(
        ({id, isTemporary}) => id === currentTemplate.id && isTemporary,
      ) ?? {}
    return {...currentTemplate, ...temporaryCurrentTemplate}
  }

  const currentProjectTemplateId = projectTemplates.find(
    ({isSelected}) => isSelected,
  )?.id

  // set current project template
  if (
    typeof currentProjectTemplateId === 'number' &&
    currentProjectTemplateId !== prevCurrentProjectTemplateId.current
  ) {
    setCurrentProjectTemplate(getCurrentTemplate())
  }

  prevCurrentProjectTemplateId.current = currentProjectTemplateId

  return {
    projectTemplates,
    currentProjectTemplate,
    setProjectTemplates,
    modifyingCurrentTemplate,
  }
}

export default useProjectTemplates
