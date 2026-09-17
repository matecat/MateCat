import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import {EditorMachineTranslationTab} from './EditorMachineTranslationTab'
import {SettingsPanelContext} from '../settingsPanel/SettingsPanelContext'
import {updateJobMetadata} from '../../api/updateJobMetadata'
import CatToolStore from '../../stores/CatToolStore'
import CatToolConstants from '../../constants/CatToolConstants'

jest.mock('../../api/updateJobMetadata', () => ({
  updateJobMetadata: jest.fn(() => Promise.resolve({})),
}))

jest.mock('../../stores/CatToolStore', () => ({
  getJobMetadata: jest.fn(),
  setJobMetadata: jest.fn(),
  emitChange: jest.fn(),
}))

jest.mock('../settingsPanel/Contents/MachineTranslationTab', () => ({
  MachineTranslationTab: (props) => (
    <div data-testid="machine-translation-tab">{JSON.stringify(props)}</div>
  ),
}))

const baseTemplate = {
  mtQualityValueInEditor: 75,
  mt: {extra: {}},
}

const renderComponent = (template = baseTemplate, props = {}) =>
  render(
    <SettingsPanelContext.Provider value={{currentProjectTemplate: template}}>
      <EditorMachineTranslationTab {...props} />
    </SettingsPanelContext.Provider>,
  )

const reRenderComponent = (rerender, template, props = {}) =>
  rerender(
    <SettingsPanelContext.Provider value={{currentProjectTemplate: template}}>
      <EditorMachineTranslationTab {...props} />
    </SettingsPanelContext.Provider>,
  )

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {is_cattool: true}
  CatToolStore.getJobMetadata.mockReturnValue({
    job: {mt_quality_value_in_editor: 75, mt_extra: {}, other_job_field: 1},
    project: {other_project_field: 2},
  })
})

describe('EditorMachineTranslationTab', () => {
  describe('rendering', () => {
    test('renders MachineTranslationTab', () => {
      renderComponent()
      expect(screen.getByTestId('machine-translation-tab')).toBeInTheDocument()
    })

    test('forwards props to MachineTranslationTab', () => {
      renderComponent(baseTemplate, {isCattoolPage: true})
      expect(screen.getByTestId('machine-translation-tab')).toHaveTextContent(
        JSON.stringify({isCattoolPage: true}),
      )
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
      reRenderComponent(rerender, {...baseTemplate, mtQualityValueInEditor: 90})
      expect(updateJobMetadata).not.toHaveBeenCalled()
    })

    test('calls updateJobMetadata when mtQualityValueInEditor changes', () => {
      const {rerender} = renderComponent()
      reRenderComponent(rerender, {...baseTemplate, mtQualityValueInEditor: 90})

      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
      expect(updateJobMetadata).toHaveBeenCalledWith({
        mtQualityValueInEditor: 90,
        mtExtra: {},
      })
    })

    test('does not call updateJobMetadata again on an unchanged re-render', () => {
      const {rerender} = renderComponent()
      const changedTemplate = {...baseTemplate, mtQualityValueInEditor: 90}
      reRenderComponent(rerender, changedTemplate)
      reRenderComponent(rerender, changedTemplate)

      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
    })

    test('only sends known engine-option keys in mtExtra, dropping unrelated ones', () => {
      const initialTemplate = {
        ...baseTemplate,
        mt: {extra: {lara_style: 'faithful', unrelated_field: 'x'}},
      }
      const {rerender} = renderComponent(initialTemplate)
      reRenderComponent(rerender, {
        ...initialTemplate,
        mt: {extra: {lara_style: 'creative', unrelated_field: 'x'}},
      })

      expect(updateJobMetadata).toHaveBeenCalledWith({
        mtQualityValueInEditor: 75,
        mtExtra: {lara_style: 'creative'},
      })
    })

    test('calls updateJobMetadata when a tracked mt.extra value changes', () => {
      const initialTemplate = {
        ...baseTemplate,
        mt: {extra: {lara_style: 'faithful'}},
      }
      const {rerender} = renderComponent(initialTemplate)
      reRenderComponent(rerender, {
        ...initialTemplate,
        mt: {extra: {lara_style: 'creative'}},
      })

      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
      expect(updateJobMetadata).toHaveBeenCalledWith({
        mtQualityValueInEditor: 75,
        mtExtra: {lara_style: 'creative'},
      })
    })

    test('calls updateJobMetadata when a tracked mt.extra value is newly set (was previously undefined)', () => {
      const initialTemplate = {...baseTemplate, mt: {extra: {}}}
      const {rerender} = renderComponent(initialTemplate)
      reRenderComponent(rerender, {
        ...initialTemplate,
        mt: {extra: {lara_style: 'faithful'}},
      })

      expect(updateJobMetadata).toHaveBeenCalledTimes(1)
      expect(updateJobMetadata).toHaveBeenCalledWith({
        mtQualityValueInEditor: 75,
        mtExtra: {lara_style: 'faithful'},
      })
    })
  })

  describe('after updateJobMetadata resolves', () => {
    test('merges the new settings into the job branch of CatToolStore metadata and re-emits it', async () => {
      const {rerender} = renderComponent()
      reRenderComponent(rerender, {
        ...baseTemplate,
        mtQualityValueInEditor: 90,
        mt: {extra: {lara_style: 'faithful'}},
      })

      await waitFor(() =>
        expect(CatToolStore.setJobMetadata).toHaveBeenCalled(),
      )

      expect(CatToolStore.setJobMetadata).toHaveBeenCalledWith({
        job: {
          mt_quality_value_in_editor: 90,
          mt_extra: {lara_style: 'faithful'},
          other_job_field: 1,
        },
        project: {other_project_field: 2},
      })
      expect(CatToolStore.emitChange).toHaveBeenCalledWith(
        CatToolConstants.GET_JOB_METADATA,
        {jobMetadata: CatToolStore.getJobMetadata()},
      )
    })

    test('does nothing when CatToolStore has no job metadata yet', async () => {
      CatToolStore.getJobMetadata.mockReturnValue(undefined)

      const {rerender} = renderComponent()
      reRenderComponent(rerender, {...baseTemplate, mtQualityValueInEditor: 90})

      await waitFor(() =>
        expect(CatToolStore.getJobMetadata).toHaveBeenCalled(),
      )

      expect(CatToolStore.setJobMetadata).not.toHaveBeenCalled()
      expect(CatToolStore.emitChange).not.toHaveBeenCalled()
    })
  })
})
