import React from 'react'
import {render, screen, within} from '@testing-library/react'
import {EditorOtherTab} from './EditorOtherTab'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {updateJobMetadata} from '../../../../api/updateJobMetadata'

jest.mock('../../../../api/updateJobMetadata', () => ({
  updateJobMetadata: jest.fn(),
}))

jest.mock('../OtherTab/Tagging', () => ({
  Tagging: () => <div data-testid="tagging" />,
}))

jest.mock('../OtherTab/MandatoryIssues', () => ({
  MandatoryIssues: () => <div data-testid="mandatory-issues" />,
}))

jest.mock('../OtherTab/CharacterCounterRules', () => ({
  CharacterCounterRules: () => <div data-testid="character-counter-rules" />,
}))

const baseTemplate = {
  characterCounterCountTags: false,
  characterCounterMode: 'chars',
  subfilteringHandlers: null,
  mandatoryIssues: [],
}

const renderComponent = (template = baseTemplate, tmKeys = []) =>
  render(
    <SettingsPanelContext.Provider
      value={{currentProjectTemplate: template, tmKeys}}
    >
      <EditorOtherTab />
    </SettingsPanelContext.Provider>,
  )

const reRenderComponent = (rerender, template, tmKeys = []) =>
  rerender(
    <SettingsPanelContext.Provider
      value={{currentProjectTemplate: template, tmKeys}}
    >
      <EditorOtherTab />
    </SettingsPanelContext.Provider>,
  )

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {is_cattool: true}
})

describe('EditorOtherTab', () => {
  describe('rendering', () => {
    test('renders outer wrapper with correct classes', () => {
      const {container} = renderComponent()
      expect(
        container.querySelector(
          '.editor-settings-options-box.settings-panel-contentwrapper-tab-background',
        ),
      ).toBeInTheDocument()
    })

    test('renders two subcategory sections', () => {
      const {container} = renderComponent()
      expect(
        container.querySelectorAll(
          '.settings-panel-contentwrapper-tab-subcategories',
        ),
      ).toHaveLength(2)
    })

    describe('General settings section', () => {
      let generalSection

      beforeEach(() => {
        const {container} = renderComponent()
        generalSection = container.querySelectorAll(
          '.settings-panel-contentwrapper-tab-subcategories',
        )[0]
      })

      test('has "General settings" heading', () => {
        expect(
          within(generalSection).getByText('General settings'),
        ).toBeInTheDocument()
      })

      test('renders Tagging', () => {
        expect(within(generalSection).getByTestId('tagging')).toBeInTheDocument()
      })

      test('renders MandatoryIssues', () => {
        expect(
          within(generalSection).getByTestId('mandatory-issues'),
        ).toBeInTheDocument()
      })

      test('does not render CharacterCounterRules', () => {
        expect(
          within(generalSection).queryByTestId('character-counter-rules'),
        ).not.toBeInTheDocument()
      })
    })

    describe('Character counter settings section', () => {
      let counterSection

      beforeEach(() => {
        const {container} = renderComponent()
        counterSection = container.querySelectorAll(
          '.settings-panel-contentwrapper-tab-subcategories',
        )[1]
      })

      test('has "Character counter settings" heading', () => {
        expect(
          within(counterSection).getByText('Character counter settings'),
        ).toBeInTheDocument()
      })

      test('renders CharacterCounterRules', () => {
        expect(
          within(counterSection).getByTestId('character-counter-rules'),
        ).toBeInTheDocument()
      })

      test('does not render general settings children', () => {
        expect(
          within(counterSection).queryByTestId('tagging'),
        ).not.toBeInTheDocument()
        expect(
          within(counterSection).queryByTestId('mandatory-issues'),
        ).not.toBeInTheDocument()
      })
    })
  })

  describe('updateJobMetadata side effects', () => {
    test('does not call updateJobMetadata on first render', () => {
      renderComponent()
      expect(updateJobMetadata).not.toHaveBeenCalled()
    })

    test('does not call updateJobMetadata when is_cattool is false', () => {
      global.config.is_cattool = false
      const {rerender} = renderComponent()
      reRenderComponent(rerender, {
        ...baseTemplate,
        characterCounterMode: 'words',
      })
      expect(updateJobMetadata).not.toHaveBeenCalled()
    })

    test('does not call updateJobMetadata when only tmKeys changes', () => {
      const {rerender} = renderComponent(baseTemplate, [])
      reRenderComponent(rerender, baseTemplate, [{id: 1}])
      expect(updateJobMetadata).not.toHaveBeenCalled()
    })

    test('calls updateJobMetadata when characterCounterMode changes', () => {
      const {rerender} = renderComponent()
      reRenderComponent(rerender, {...baseTemplate, characterCounterMode: 'words'})
      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
      expect(updateJobMetadata).toHaveBeenCalledWith(
        expect.objectContaining({characterCounterMode: 'words'}),
      )
    })

    test('calls updateJobMetadata when characterCounterCountTags changes', () => {
      const {rerender} = renderComponent()
      reRenderComponent(rerender, {...baseTemplate, characterCounterCountTags: true})
      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
      expect(updateJobMetadata).toHaveBeenCalledWith(
        expect.objectContaining({characterCounterCountTags: true}),
      )
    })

    test('calls updateJobMetadata when subfilteringHandlers changes', () => {
      const {rerender} = renderComponent()
      const handlers = {handler: 'test'}
      reRenderComponent(rerender, {...baseTemplate, subfilteringHandlers: handlers})
      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
      expect(updateJobMetadata).toHaveBeenCalledWith(
        expect.objectContaining({subfilteringHandlers: handlers}),
      )
    })

    test('calls updateJobMetadata when mandatoryIssues changes', () => {
      const {rerender} = renderComponent()
      const issues = [{id: 1, name: 'issue'}]
      reRenderComponent(rerender, {...baseTemplate, mandatoryIssues: issues})
      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
      expect(updateJobMetadata).toHaveBeenCalledWith(
        expect.objectContaining({mandatoryIssues: issues}),
      )
    })

    test('passes all template fields to updateJobMetadata', () => {
      const {rerender} = renderComponent()
      const newTemplate = {
        characterCounterCountTags: true,
        characterCounterMode: 'words',
        subfilteringHandlers: {handler: 'test'},
        mandatoryIssues: [{id: 1}],
      }
      reRenderComponent(rerender, newTemplate)
      expect(updateJobMetadata).toHaveBeenCalledWith({
        characterCounterCountTags: true,
        characterCounterMode: 'words',
        subfilteringHandlers: {handler: 'test'},
        mandatoryIssues: [{id: 1}],
      })
    })

    test('calls updateJobMetadata only once per change, not on unchanged re-renders', () => {
      const {rerender} = renderComponent()
      const changedTemplate = {...baseTemplate, characterCounterMode: 'words'}
      reRenderComponent(rerender, changedTemplate)
      reRenderComponent(rerender, changedTemplate)
      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
    })
  })
})
