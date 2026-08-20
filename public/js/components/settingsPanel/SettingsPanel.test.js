import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {SettingsPanel} from './SettingsPanel'
import {SettingsPanelContext} from './SettingsPanelContext'
import CreateProjectStore from '../../stores/CreateProjectStore'
import NewProjectConstants from '../../constants/NewProjectConstants'
import {updateProjectTemplate} from '../../api/updateProjectTemplate'
import useSyncTemplateWithConvertFile from './useSyncTemplateWithConvertFile'
import ModalsActions from '../../actions/ModalsActions'

// ContentWrapper and Tab are lightweight and already well covered elsewhere;
// keeping them real here lets SettingsPanel's tab wiring (active tab,
// isEnabledProjectTemplateComponent) be exercised end to end. Only the heavy
// per-tab content components are stubbed out.
jest.mock('./Contents/MachineTranslationTab', () => ({
  MachineTranslationTab: () => <div data-testid="mt-tab" />,
}))
jest.mock('./Contents/OtherTab', () => ({
  OtherTab: () => <div data-testid="other-tab" />,
}))
jest.mock('./Contents/TranslationMemoryGlossaryTab', () => {
  const ReactLib = require('react')
  const {SettingsPanelContext: Ctx} = require('./SettingsPanelContext')
  return {
    TranslationMemoryGlossaryTab: () => {
      const {openLoginModal} = ReactLib.useContext(Ctx)
      return (
        <div data-testid="tm-tab">
          <button onClick={openLoginModal}>open-login</button>
        </div>
      )
    },
  }
})
jest.mock('./ProjectTemplate/ProjectTemplate', () => ({
  ProjectTemplate: () => <div data-testid="project-template" />,
}))
jest.mock('./Contents/AnalysisTab', () => ({
  AnalysisTab: () => <div data-testid="analysis-tab" />,
}))
jest.mock('./Contents/QualityFrameworkTab', () => ({
  QualityFrameworkTab: () => <div data-testid="qf-tab" />,
}))
jest.mock('./Contents/FileImportTab/FileImportTab', () => ({
  FileImportTab: () => <div data-testid="file-import-tab" />,
}))
jest.mock('./Contents/EditorSettingsTab', () => ({
  EditorSettingsTab: () => <div data-testid="editor-settings-tab" />,
}))
jest.mock('./Contents/EditorOtherTab', () => ({
  EditorOtherTab: () => <div data-testid="editor-other-tab" />,
}))

jest.mock('../../api/updateProjectTemplate', () => ({
  updateProjectTemplate: jest.fn(() => Promise.resolve({})),
}))
jest.mock('../../api/getFiltersParamsTemplates', () => ({
  getFiltersParamsTemplates: jest.fn(() => Promise.resolve({})),
}))
jest.mock('./useSyncTemplateWithConvertFile', () => ({
  __esModule: true,
  default: jest.fn(),
}))
jest.mock('../../actions/ModalsActions', () => ({
  openLoginModal: jest.fn(),
}))

jest.mock('../../stores/CreateProjectStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
  getFiltersTemplate: jest.fn(() => ({})),
  updateProject: jest.fn(),
}))

const baseProps = () => ({
  onClose: jest.fn(),
  isOpened: true,
  user: {},
  tmKeys: [],
  setTmKeys: jest.fn(),
  mtEngines: [],
  setMtEngines: jest.fn(),
  sourceLang: {code: 'en-US', name: 'English'},
  targetLangs: [{code: 'it-IT', name: 'Italian'}],
  projectTemplates: [],
  currentProjectTemplate: {id: 1, filtersTemplateId: 2},
  setProjectTemplates: jest.fn(),
  modifyingCurrentTemplate: jest.fn(),
  checkSpecificTemplatePropsAreModified: jest.fn(() => false),
  subtemplatesNotSaved: [],
})

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {is_cattool: false, ownerIsMe: true}
})

describe('rendering', () => {
  test('renders header, ProjectTemplate and the active tab content when opened with a template', () => {
    const {container} = render(<SettingsPanel {...baseProps()} />)

    expect(container.querySelector('.settings-panel-header')).toBeInTheDocument()
    expect(screen.getByText('Settings')).toBeInTheDocument()
    expect(screen.getByTestId('project-template')).toBeInTheDocument()
    expect(
      container.querySelector('.settings-panel-contentwrapper'),
    ).toBeInTheDocument()
    expect(screen.getByTestId('tm-tab')).toBeInTheDocument()
  })

  test('does not render header content when isOpened is false', () => {
    const {container} = render(
      <SettingsPanel {...baseProps()} isOpened={false} />,
    )

    expect(
      container.querySelector('.settings-panel-header'),
    ).not.toBeInTheDocument()
    expect(screen.queryByTestId('project-template')).not.toBeInTheDocument()
    expect(
      container.querySelector('.settings-panel-contentwrapper'),
    ).not.toBeInTheDocument()
  })

  test('treats an undefined isOpened as visible, but only renders tab content when isOpened is truthy', () => {
    const props = baseProps()
    delete props.isOpened
    const {container} = render(<SettingsPanel {...props} />)

    // The outer wrapper is visible by default...
    expect(
      container.querySelector('.settings-panel.visible'),
    ).toBeInTheDocument()
    // ...but the header/tabs only render when isOpened is strictly truthy.
    expect(screen.queryByTestId('project-template')).not.toBeInTheDocument()
  })

  test('does not render ProjectTemplate when config.is_cattool is true', () => {
    global.config.is_cattool = true
    render(<SettingsPanel {...baseProps()} />)

    expect(screen.queryByTestId('project-template')).not.toBeInTheDocument()
    expect(screen.getByTestId('tm-tab')).toBeInTheDocument()
  })

  test('does not render tab content when there is no current project template', () => {
    const {container} = render(
      <SettingsPanel {...baseProps()} currentProjectTemplate={undefined} />,
    )

    expect(
      container.querySelector('.settings-panel-contentwrapper'),
    ).not.toBeInTheDocument()
    expect(screen.getByTestId('project-template')).toBeInTheDocument()
  })

  test('applies the without-project-template-control class when project template is disabled', () => {
    global.config.is_cattool = true
    const {container} = render(<SettingsPanel {...baseProps()} />)

    expect(
      container.querySelector(
        '.settings-panel-contentwrapper-container-without-project-teamplate-control',
      ),
    ).toBeInTheDocument()
  })

  test('does not apply the without-project-template-control class when project template is enabled', () => {
    const {container} = render(<SettingsPanel {...baseProps()} />)

    expect(
      container.querySelector(
        '.settings-panel-contentwrapper-container-without-project-teamplate-control',
      ),
    ).not.toBeInTheDocument()
  })
})

describe('closing interactions', () => {
  test('clicking the overlay hides the panel and eventually calls onClose on transition end', () => {
    const props = baseProps()
    const {container} = render(<SettingsPanel {...props} />)

    const overlay = container.querySelector('.settings-panel-overlay')
    fireEvent.click(overlay)

    const wrapper = container.querySelector('.settings-panel-wrapper')
    expect(wrapper).toHaveClass('settings-panel-wrapper-hide')

    fireEvent.transitionEnd(wrapper)

    expect(props.onClose).toHaveBeenCalledTimes(1)
  })

  test('pressing Escape hides the panel and triggers onClose on transition end', () => {
    const props = baseProps()
    const {container} = render(<SettingsPanel {...props} />)

    fireEvent.keyDown(document, {key: 'Escape'})

    const wrapper = container.querySelector('.settings-panel-wrapper')
    expect(wrapper).toHaveClass('settings-panel-wrapper-hide')

    fireEvent.transitionEnd(wrapper)

    expect(props.onClose).toHaveBeenCalledTimes(1)
  })

  test('clicking the close button hides the panel', async () => {
    const props = baseProps()
    const {container} = render(<SettingsPanel {...props} />)

    const closeButton = container.querySelector(
      '.settings-panel-header button',
    )
    await userEvent.click(closeButton)

    const wrapper = container.querySelector('.settings-panel-wrapper')
    expect(wrapper).toHaveClass('settings-panel-wrapper-hide')
  })

  test('does not call onClose on transition end while still visible', () => {
    const props = baseProps()
    const {container} = render(<SettingsPanel {...props} />)

    const wrapper = container.querySelector('.settings-panel-wrapper')
    fireEvent.transitionEnd(wrapper)

    expect(props.onClose).not.toHaveBeenCalled()
  })

  test('removes the keydown and store listeners on unmount', () => {
    const removeSpy = jest.spyOn(document, 'removeEventListener')
    const props = baseProps()
    const {unmount} = render(<SettingsPanel {...props} />)

    unmount()

    expect(removeSpy).toHaveBeenCalledWith('keydown', expect.any(Function))
    expect(CreateProjectStore.removeListener).toHaveBeenCalledWith(
      NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
      expect.any(Function),
    )
    removeSpy.mockRestore()
  })
})

describe('openLoginModal (exposed via SettingsPanelContext)', () => {
  test('hides the panel and delegates to ModalsActions.openLoginModal', async () => {
    const props = baseProps()
    const {container} = render(<SettingsPanel {...props} />)

    await userEvent.click(screen.getByText('open-login'))

    expect(ModalsActions.openLoginModal).toHaveBeenCalledTimes(1)
    const wrapper = container.querySelector('.settings-panel-wrapper')
    expect(wrapper).toHaveClass('settings-panel-wrapper-hide')
  })
})

describe('template sync setup', () => {
  test('wires useSyncTemplateWithConvertFile with the current template ids', () => {
    const props = baseProps()
    render(<SettingsPanel {...props} />)

    expect(useSyncTemplateWithConvertFile).toHaveBeenCalledWith(
      expect.objectContaining({
        idProjectTemplate: 1,
        idTemplate: 2,
        checkIfUpdate: expect.any(Function),
      }),
    )
  })

  test('checkIfUpdate updates the store when the filters template actually changed', () => {
    CreateProjectStore.getFiltersTemplate.mockReturnValue({a: 1})
    const props = baseProps()
    render(<SettingsPanel {...props} />)

    const {checkIfUpdate} = useSyncTemplateWithConvertFile.mock.calls[0][0]
    checkIfUpdate({a: 2})

    expect(CreateProjectStore.updateProject).toHaveBeenCalledWith({
      filtersTemplate: {a: 2},
    })
  })

  test('checkIfUpdate is a no-op when the filters template did not change', () => {
    CreateProjectStore.getFiltersTemplate.mockReturnValue({a: 1})
    const props = baseProps()
    render(<SettingsPanel {...props} />)

    const {checkIfUpdate} = useSyncTemplateWithConvertFile.mock.calls[0][0]
    checkIfUpdate({a: 1})

    expect(CreateProjectStore.updateProject).not.toHaveBeenCalled()
  })
})

describe('CreateProjectStore UPDATE_PROJECT_TEMPLATES listener', () => {
  const getRegisteredListener = () => {
    const call = CreateProjectStore.addListener.mock.calls.find(
      ([event]) => event === NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
    )
    return call[1]
  }

  test('registers the listener on mount', () => {
    render(<SettingsPanel {...baseProps()} />)

    expect(CreateProjectStore.addListener).toHaveBeenCalledWith(
      NewProjectConstants.UPDATE_PROJECT_TEMPLATES,
      expect.any(Function),
    )
  })

  test('updates project templates and merges the current template on resolution', async () => {
    updateProjectTemplate.mockResolvedValue({
      id: 5,
      modified_at: '2024-01-01',
      name: 'updated',
    })
    const props = baseProps()
    props.currentProjectTemplate = {id: 5, isTemporary: false}
    render(<SettingsPanel {...props} />)

    const listener = getRegisteredListener()

    await act(async () => {
      listener({
        templates: [
          {id: 5, isTemporary: false, name: 'old'},
          {id: 0, isTemporary: false, name: 'standard'},
          {id: 6, isTemporary: true, name: 'temp'},
        ],
        modifiedPropsCurrentProjectTemplate: {name: 'draft'},
      })
      await Promise.resolve()
      await Promise.resolve()
    })

    expect(updateProjectTemplate).toHaveBeenCalledWith({
      id: 5,
      template: {name: 'old'},
    })
    expect(props.setProjectTemplates).toHaveBeenCalledWith(
      expect.any(Function),
    )

    const updater = props.setProjectTemplates.mock.calls[0][0]
    const nextState = updater([
      {id: 5, name: 'old'},
      {id: 0, name: 'standard'},
    ])
    expect(nextState).toEqual([
      {id: 5, name: 'updated', modified_at: '2024-01-01'},
      {id: 0, name: 'standard'},
    ])

    expect(props.modifyingCurrentTemplate).toHaveBeenCalledWith(
      expect.any(Function),
    )
  })

  test('modifyingCurrentTemplate merges draft props when a temporary template is present', async () => {
    updateProjectTemplate.mockResolvedValue({
      id: 5,
      modified_at: '2024-02-02',
    })
    const props = baseProps()
    props.currentProjectTemplate = {id: 5, isTemporary: false}
    render(<SettingsPanel {...props} />)

    const listener = getRegisteredListener()

    await act(async () => {
      listener({
        templates: [{id: 6, isTemporary: true}],
        modifiedPropsCurrentProjectTemplate: {name: 'draft'},
      })
      await Promise.resolve()
      await Promise.resolve()
    })

    const updateFn = props.modifyingCurrentTemplate.mock.calls[0][0]
    const result = updateFn({name: 'previous'})
    expect(result).toEqual(expect.objectContaining({name: 'draft'}))
  })

  test('modifyingCurrentTemplate falls back to the resolved original template otherwise', async () => {
    updateProjectTemplate.mockResolvedValue({
      id: 5,
      isTemporary: false,
      name: 'resolved',
    })
    const props = baseProps()
    props.currentProjectTemplate = {id: 5, isTemporary: false}
    render(<SettingsPanel {...props} />)

    const listener = getRegisteredListener()

    await act(async () => {
      listener({
        templates: [{id: 5, isTemporary: false, name: 'old'}],
        modifiedPropsCurrentProjectTemplate: {name: 'draft'},
      })
      await Promise.resolve()
      await Promise.resolve()
    })

    const updateFn = props.modifyingCurrentTemplate.mock.calls[0][0]
    const result = updateFn({name: 'previous'})
    expect(result).toEqual({id: 5, isTemporary: false, name: 'resolved'})
  })
})
