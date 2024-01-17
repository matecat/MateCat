import React, {useContext, useEffect} from 'react'
import {Select} from '../common/Select'
import {SettingsPanelContext} from './SettingsPanelContext'

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
            key: 'a3ef859c24f7b4d4d33a',
            name: 'test prova mauro',
            owner: true,
            tm: true,
            r: true,
            w: false,
          },
        ],
      },
    ])
  })

export const ProjectTemplate = () => {
  const {projectTemplates, setProjectTemplates} =
    useContext(SettingsPanelContext)

  useEffect(() => {
    let cleanup = false

    getProjectTemplates().then((data) => {
      if (!cleanup) {
        setProjectTemplates(
          data.map((template) => ({
            ...template,
            isSelected: template.is_default,
          })),
        )
      }
    })

    return () => (cleanup = true)
  }, [setProjectTemplates])

  const activeTemplate = projectTemplates.find(({isSelected}) => isSelected)
  const options = projectTemplates.map(({id, name}) => ({
    id: id.toString(),
    name,
  }))
  const activeOption = activeTemplate && {
    id: activeTemplate.id.toString(),
    name: activeTemplate.name,
  }

  const onSelect = (option) =>
    setProjectTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === parseInt(option.id),
      })),
    )

  return (
    <div className="settings-panel-project-template">
      {options.length > 0 && (
        <Select
          placeholder="Select template"
          label="Project template"
          id="project-template"
          maxHeightDroplist={100}
          options={options}
          activeOption={activeOption}
          onSelect={onSelect}
        />
      )}
    </div>
  )
}
