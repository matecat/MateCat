import React from 'react'
import {render, screen} from '@testing-library/react'
import {FileImportTab} from './FileImportTab'
import {SettingsPanelContext} from '../../SettingsPanelContext'

beforeAll(() => {
  global.config = {is_cattool: true}
})

const setup = () => {
  const modifyingCurrentTemplate = jest.fn()
  const value = {
    currentProjectTemplate: {segmentationRule: {id: 'standard'}},
    modifyingCurrentTemplate,
    fileImportFiltersParamsTemplates: {
      templates: [],
      setTemplates: () => {},
      currentTemplate: undefined,
      modifyingCurrentTemplate: () => {},
    },
    fileImportXliffSettingsTemplates: {
      templates: [],
      setTemplates: () => {},
      currentTemplate: undefined,
      modifyingCurrentTemplate: () => {},
    },
    portalTarget: document.body,
  }

  const utils = render(
    <SettingsPanelContext.Provider value={value}>
      <FileImportTab />
    </SettingsPanelContext.Provider>,
  )
  return {modifyingCurrentTemplate, ...utils}
}

describe('FileImportTab', () => {
  test('renders the segmentation rule section', () => {
    setup()

    expect(screen.getByText('Segmentation rules')).toBeInTheDocument()
    expect(
      screen.getByTestId('container-segmentationrule'),
    ).toBeInTheDocument()
  })

  test('does not render extraction parameters or xliff sections when there are no templates', () => {
    setup()

    expect(screen.queryByText('Extraction parameters')).not.toBeInTheDocument()
    expect(screen.queryByText('XLIFF import settings')).not.toBeInTheDocument()
  })
})
