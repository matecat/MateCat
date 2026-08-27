import React, {useState} from 'react'
import {render, screen} from '@testing-library/react'
import {XliffSettings} from './XliffSettings'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import defaultXliffSettings from '../../defaultTemplates/xliffSettings.json'

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

beforeAll(() => {
  window.ResizeObserver = ResizeObserver
  global.config = {is_cattool: true}
})

const toClientTemplate = (template) => ({
  id: template.id,
  uid: template.uid,
  name: template.name,
  isTemporary: false,
  isSelected: true,
  rules: template.rules,
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
        fileImportXliffSettingsTemplates: {
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
      <XliffSettings />
    </SettingsPanelContext.Provider>
  )
}

describe('XliffSettings', () => {
  test('renders nothing when there are no templates', () => {
    render(<Wrapper templates={[]} currentProjectTemplate={{}} />)

    expect(
      screen.queryByText('XLIFF import settings'),
    ).not.toBeInTheDocument()
  })

  test('renders the xliff import settings section with both rule tables', () => {
    render(
      <Wrapper
        templates={[toClientTemplate(defaultXliffSettings)]}
        currentProjectTemplate={{
          XliffConfigTemplateId: defaultXliffSettings.id,
        }}
      />,
    )

    expect(screen.getByText('XLIFF import settings')).toBeInTheDocument()
    expect(screen.getByText('XLIFF 1.2')).toBeInTheDocument()
    expect(screen.getByText('XLIFF 2.0')).toBeInTheDocument()
  })
})
