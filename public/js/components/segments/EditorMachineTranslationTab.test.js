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

let modifyingCurrentTemplate

const renderComponent = (template = baseTemplate, props = {}) =>
  render(
    <SettingsPanelContext.Provider
      value={{currentProjectTemplate: template, modifyingCurrentTemplate}}
    >
      <EditorMachineTranslationTab {...props} />
    </SettingsPanelContext.Provider>,
  )

const reRenderComponent = (rerender, template, props = {}) =>
  rerender(
    <SettingsPanelContext.Provider
      value={{currentProjectTemplate: template, modifyingCurrentTemplate}}
    >
      <EditorMachineTranslationTab {...props} />
    </SettingsPanelContext.Provider>,
  )

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {is_cattool: true}
  modifyingCurrentTemplate = jest.fn()
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

  describe('intento double-key workaround', () => {
    test('calls modifyingCurrentTemplate once on mount when mt.extra is defined', () => {
      renderComponent()

      expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
      expect(modifyingCurrentTemplate).toHaveBeenCalledWith(
        expect.any(Function),
      )
    })

    test('does not call modifyingCurrentTemplate when CatToolStore has no job metadata yet', () => {
      CatToolStore.getJobMetadata.mockReturnValue(undefined)

      renderComponent()

      expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
    })

    test('runs only once even across re-renders', () => {
      const {rerender} = renderComponent()
      reRenderComponent(rerender, {...baseTemplate, mtQualityValueInEditor: 90})
      reRenderComponent(rerender, {...baseTemplate, mtQualityValueInEditor: 95})

      expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    })

    const runUpdater = (prevExtra) => {
      const updater = modifyingCurrentTemplate.mock.calls[0][0]
      return updater({mt: {id: 1, extra: prevExtra}})
    }

    test('keeps the job intento_provider when both job keys are set', () => {
      CatToolStore.getJobMetadata.mockReturnValue({
        job: {
          mt_extra: {
            intento_provider: 'job-provider',
            intento_routing: 'job-routing',
          },
        },
        project: {},
      })
      renderComponent()

      const result = runUpdater({
        intento_provider: 'stale',
        intento_routing: 'stale',
        lara_style: 'faithful',
      })

      expect(result.mt.extra).toEqual({
        lara_style: 'faithful',
        intento_provider: 'job-provider',
      })
    })

    test('falls back to the job intento_routing when the job provider is empty', () => {
      CatToolStore.getJobMetadata.mockReturnValue({
        job: {mt_extra: {intento_provider: '', intento_routing: 'job-routing'}},
        project: {},
      })
      renderComponent()

      const result = runUpdater({intento_provider: 'stale'})

      expect(result.mt.extra).toEqual({intento_routing: 'job-routing'})
    })

    test('falls back to the project intento_provider when neither job key is set', () => {
      CatToolStore.getJobMetadata.mockReturnValue({
        job: {mt_extra: {}},
        project: {mt_extra: {intento_provider: 'project-provider'}},
      })
      renderComponent()

      const result = runUpdater({})

      expect(result.mt.extra).toEqual({intento_provider: 'project-provider'})
    })

    test('falls back to the project intento_routing as a last resort', () => {
      CatToolStore.getJobMetadata.mockReturnValue({
        job: {mt_extra: {}},
        project: {mt_extra: {intento_routing: 'project-routing'}},
      })
      renderComponent()

      const result = runUpdater({})

      expect(result.mt.extra).toEqual({intento_routing: 'project-routing'})
    })

    test('drops both intento keys when neither job nor project has one set', () => {
      CatToolStore.getJobMetadata.mockReturnValue({
        job: {mt_extra: {}},
        project: {mt_extra: {}},
      })
      renderComponent()

      const result = runUpdater({
        intento_provider: 'stale',
        lara_style: 'faithful',
      })

      expect(result.mt.extra).toEqual({lara_style: 'faithful'})
    })

    test('preserves the rest of prevTemplate untouched', () => {
      CatToolStore.getJobMetadata.mockReturnValue({
        job: {mt_extra: {}},
        project: {},
      })
      renderComponent()

      const updater = modifyingCurrentTemplate.mock.calls[0][0]
      const result = updater({
        id: 9,
        mtQualityValueInEditor: 80,
        mt: {id: 1, extra: {}},
      })

      expect(result).toMatchObject({
        id: 9,
        mtQualityValueInEditor: 80,
        mt: {id: 1},
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
