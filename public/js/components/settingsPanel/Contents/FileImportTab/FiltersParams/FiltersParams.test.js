import React, {useState} from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {FiltersParams} from './FiltersParams'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import defaultFilterParams from '../../defaultTemplates/filterParams.json'

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

beforeAll(() => {
  window.ResizeObserver = ResizeObserver
})

const toClientTemplate = (template) => ({
  id: template.id,
  uid: template.uid,
  name: template.name,
  isTemporary: false,
  isSelected: true,
  xml: template.xml,
  yaml: template.yaml,
  json: template.json,
  msWord: template.ms_word,
  msExcel: template.ms_excel,
  msPowerpoint: template.ms_powerpoint,
  dita: template.dita,
})

const Wrapper = ({templates: initialTemplates, currentProjectTemplate}) => {
  const [templates, setTemplates] = useState(initialTemplates)
  const [projectTemplate, setProjectTemplate] = useState(currentProjectTemplate)

  const currentTemplate = templates.find(({isSelected}) => isSelected)

  return (
    <SettingsPanelContext.Provider
      value={{
        currentProjectTemplate: projectTemplate,
        modifyingCurrentTemplate: (updater) =>
          setProjectTemplate((prev) => updater(prev)),
        fileImportFiltersParamsTemplates: {
          templates,
          setTemplates,
          currentTemplate,
          modifyingCurrentTemplate: (updater) =>
            setTemplates((prev) =>
              prev.map((template) =>
                template.isSelected ? updater(template) : template,
              ),
            ),
        },
        projectTemplates: [],
        setProjectTemplates: () => {},
        portalTarget: document.body,
      }}
    >
      <button
        type="button"
        onClick={() =>
          setTemplates((prev) =>
            prev.map((template) => ({
              ...template,
              isSelected: template.id === 5,
            })),
          )
        }
      >
        select-other-template
      </button>
      <span data-testid="project-filters-template-id">
        {projectTemplate.filtersTemplateId}
      </span>
      <FiltersParams />
    </SettingsPanelContext.Provider>
  )
}

describe('FiltersParams', () => {
  test('renders nothing when there are no templates', () => {
    render(<Wrapper templates={[]} currentProjectTemplate={{}} />)

    expect(screen.queryByText('Extraction parameters')).not.toBeInTheDocument()
  })

  test('renders the extraction parameters section and format accordions', () => {
    render(
      <Wrapper
        templates={[toClientTemplate(defaultFilterParams)]}
        currentProjectTemplate={{filtersTemplateId: defaultFilterParams.id}}
      />,
    )

    expect(screen.getByText('Extraction parameters')).toBeInTheDocument()
    expect(screen.getByText('JSON')).toBeInTheDocument()
    expect(screen.getByText('XML')).toBeInTheDocument()
    expect(screen.getByText('YAML')).toBeInTheDocument()
    expect(screen.getByText('MS Word')).toBeInTheDocument()
    expect(screen.getByText('MS Excel')).toBeInTheDocument()
    expect(screen.getByText('MS PowerPoint')).toBeInTheDocument()
    expect(screen.getByText('DITA/DITAMAP')).toBeInTheDocument()
  })

  test('propagates a newly selected filters template id to the project template', () => {
    const secondTemplate = {
      ...toClientTemplate(defaultFilterParams),
      id: 5,
      isSelected: false,
    }

    render(
      <Wrapper
        templates={[toClientTemplate(defaultFilterParams), secondTemplate]}
        currentProjectTemplate={{filtersTemplateId: defaultFilterParams.id}}
      />,
    )

    expect(screen.getByTestId('project-filters-template-id')).toHaveTextContent(
      '0',
    )

    fireEvent.click(screen.getByText('select-other-template'))

    expect(screen.getByTestId('project-filters-template-id')).toHaveTextContent(
      '5',
    )
  })
})
