import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {SubTemplateMoreMenu} from './SubTemplateMoreMenu'
import {SubTemplatesContext} from './SubTemplateContext'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import ModalsActions from '../../../../actions/ModalsActions'

jest.mock('../../../../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
}))

jest.mock('../../../common/DropdownMenu/DropdownMenu', () => ({
  DropdownMenu: ({toggleButtonProps, items}) => (
    <div>
      <button data-testid={toggleButtonProps.testId}>
        {toggleButtonProps.children}
      </button>
      {items.map((item, index) => (
        <button
          key={index}
          data-testid={item.testId ?? `menu-item-${index}`}
          disabled={item.disabled}
          onClick={item.onClick}
        >
          {item.label}
        </button>
      ))}
    </div>
  ),
}))

const buildSettingsPanelContext = (overrides = {}) => ({
  projectTemplates: [],
  setProjectTemplates: jest.fn(),
  modifyingCurrentTemplate: jest.fn(),
  ...overrides,
})

const buildSubTemplatesContext = (overrides = {}) => ({
  setTemplates: jest.fn(),
  currentTemplate: {id: 1, name: 'My Template'},
  isRequestInProgress: false,
  setIsRequestInProgress: jest.fn(),
  setTemplateModifier: jest.fn(),
  setTemplateName: jest.fn(),
  propConnectProjectTemplate: SCHEMA_KEYS.qaModelTemplateId,
  deleteApi: jest.fn(() => Promise.resolve({id: 1})),
  ...overrides,
})

const renderMenu = ({settingsPanel, subTemplates} = {}) =>
  render(
    <SettingsPanelContext.Provider
      value={buildSettingsPanelContext(settingsPanel)}
    >
      <SubTemplatesContext.Provider
        value={buildSubTemplatesContext(subTemplates)}
      >
        <SubTemplateMoreMenu />
      </SubTemplatesContext.Provider>
    </SettingsPanelContext.Provider>,
  )

describe('SubTemplateMoreMenu', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('renders the toggle button and menu items', () => {
    renderMenu()
    expect(screen.getByTestId('subtemplates-more-menu')).toBeInTheDocument()
    expect(screen.getByText('Rename')).toBeInTheDocument()
    expect(screen.getByTestId('delete-template')).toBeInTheDocument()
  })

  test('disables the menu items while a request is in progress', () => {
    renderMenu({subTemplates: {isRequestInProgress: true}})
    expect(screen.getByText('Rename')).toBeDisabled()
    expect(screen.getByTestId('delete-template')).toBeDisabled()
  })

  test('does not disable the menu items when no request is in progress', () => {
    renderMenu({subTemplates: {isRequestInProgress: false}})
    expect(screen.getByText('Rename')).not.toBeDisabled()
    expect(screen.getByTestId('delete-template')).not.toBeDisabled()
  })

  test('clicking Rename switches to update mode and preloads the current name', () => {
    const setTemplateModifier = jest.fn()
    const setTemplateName = jest.fn()
    renderMenu({
      subTemplates: {
        setTemplateModifier,
        setTemplateName,
        currentTemplate: {id: 2, name: 'Existing Name'},
      },
    })

    fireEvent.click(screen.getByText('Rename'))

    expect(setTemplateModifier).toHaveBeenCalledWith('update')
    expect(setTemplateName).toHaveBeenCalledWith('Existing Name')
  })

  test('deletes immediately when no other project template references it', async () => {
    const setTemplates = jest.fn()
    const setIsRequestInProgress = jest.fn()
    const deleteApi = jest.fn(() => Promise.resolve({id: 5}))
    renderMenu({
      settingsPanel: {
        projectTemplates: [
          {
            id: 'ptX',
            isTemporary: false,
            isSelected: true,
            qa_model_template_id: 999,
          },
        ],
      },
      subTemplates: {
        currentTemplate: {id: 5, name: 'Foo'},
        setTemplates,
        setIsRequestInProgress,
        deleteApi,
      },
    })

    await act(async () => {
      fireEvent.click(screen.getByTestId('delete-template'))
    })

    expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
    expect(deleteApi).toHaveBeenCalledWith(5)
    expect(setIsRequestInProgress).toHaveBeenCalledWith(true)
    expect(setIsRequestInProgress).toHaveBeenCalledWith(false)

    const updater = setTemplates.mock.calls[0][0]
    const result = updater([
      {id: 5, isTemporary: false},
      {id: 6, isTemporary: false},
    ])
    expect(result).toEqual([{id: 6, isTemporary: false, isSelected: false}])
  })

  test('logs the error and clears the request flag when deleteApi rejects', async () => {
    const setIsRequestInProgress = jest.fn()
    const deleteApi = jest.fn(() => Promise.reject(new Error('boom')))
    const consoleSpy = jest.spyOn(console, 'log').mockImplementation(() => {})
    renderMenu({
      subTemplates: {
        currentTemplate: {id: 5, name: 'Foo'},
        setIsRequestInProgress,
        deleteApi,
      },
    })

    await act(async () => {
      fireEvent.click(screen.getByTestId('delete-template'))
    })

    expect(consoleSpy).toHaveBeenCalledWith(expect.any(Error))
    expect(setIsRequestInProgress).toHaveBeenCalledWith(false)
    consoleSpy.mockRestore()
  })

  test('shows a confirmation modal when other project templates reference it', () => {
    const projectTemplates = [
      {
        id: 'pt-1',
        isTemporary: false,
        qa_model_template_id: 1,
      },
    ]
    renderMenu({
      settingsPanel: {projectTemplates},
      subTemplates: {
        currentTemplate: {id: 1, name: 'Foo'},
        propConnectProjectTemplate: SCHEMA_KEYS.qaModelTemplateId,
      },
    })

    fireEvent.click(screen.getByTestId('delete-template'))

    expect(ModalsActions.showModalComponent).toHaveBeenCalledTimes(1)
    const [, props, title] = ModalsActions.showModalComponent.mock.calls[0]
    expect(title).toBe('Confirm deletion')
    expect(props.projectTemplatesInvolved).toEqual(projectTemplates)
    expect(props.content).toContain('quality framework')
    expect(typeof props.successCallback).toBe('function')
  })

  test.each([
    [SCHEMA_KEYS.payableRateTemplateId, 'analysis'],
    [SCHEMA_KEYS.filtersTemplateId, 'extraction parameters'],
    [SCHEMA_KEYS.XliffConfigTemplateId, 'XLIFF import settings'],
    ['some_other_key', ''],
  ])(
    'builds the confirmation content for %s',
    (propConnectProjectTemplate, expectedFragment) => {
      const projectTemplates = [
        {
          id: 'pt-1',
          isTemporary: false,
          [propConnectProjectTemplate]: 1,
        },
      ]
      renderMenu({
        settingsPanel: {projectTemplates},
        subTemplates: {
          currentTemplate: {id: 1, name: 'Foo'},
          propConnectProjectTemplate,
        },
      })

      fireEvent.click(screen.getByTestId('delete-template'))

      const [, props] = ModalsActions.showModalComponent.mock.calls[0]
      if (expectedFragment) {
        expect(props.content).toContain(expectedFragment)
      } else {
        expect(props.content).toBe(
          'The  template you are about to delete is used in the following project creation template(s):',
        )
      }
    },
  )

  test('the modal successCallback performs the deletion', async () => {
    const deleteApi = jest.fn(() => Promise.resolve({id: 1}))
    const projectTemplates = [
      {
        id: 'pt-1',
        isTemporary: false,
        isSelected: true,
        qa_model_template_id: 1,
      },
    ]
    renderMenu({
      settingsPanel: {projectTemplates},
      subTemplates: {
        currentTemplate: {id: 1, name: 'Foo'},
        propConnectProjectTemplate: SCHEMA_KEYS.qaModelTemplateId,
        deleteApi,
      },
    })

    fireEvent.click(screen.getByTestId('delete-template'))

    const [, props] = ModalsActions.showModalComponent.mock.calls[0]
    await act(async () => {
      props.successCallback()
    })

    expect(deleteApi).toHaveBeenCalledWith(1)
  })

  test('cleans up references and current template when the deleted template was the active one', async () => {
    const setProjectTemplates = jest.fn()
    const modifyingCurrentTemplate = jest.fn()
    // Referenced subtemplate id (999) differs from currentTemplate.id (5)
    // so these entries are not "involved" and the delete proceeds without
    // a confirmation modal, while still exercising the post-delete cleanup
    // against the id the deleteApi call resolves with.
    const projectTemplates = [
      {
        id: 'pt-1',
        isTemporary: false,
        isSelected: true,
        qa_model_template_id: 999,
      },
      {
        id: 'pt-2',
        isTemporary: false,
        isSelected: false,
        qa_model_template_id: 999,
      },
    ]
    const deleteApi = jest.fn(() => Promise.resolve({id: 999}))
    renderMenu({
      settingsPanel: {
        projectTemplates,
        setProjectTemplates,
        modifyingCurrentTemplate,
      },
      subTemplates: {
        currentTemplate: {id: 5, name: 'Foo'},
        propConnectProjectTemplate: SCHEMA_KEYS.qaModelTemplateId,
        deleteApi,
      },
    })

    await act(async () => {
      fireEvent.click(screen.getByTestId('delete-template'))
    })

    expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
    expect(setProjectTemplates).toHaveBeenCalledTimes(1)
    const updater = setProjectTemplates.mock.calls[0][0]
    const updated = updater(projectTemplates)
    expect(updated[0].qa_model_template_id).toBe(0)
    expect(updated[1].qa_model_template_id).toBe(0)

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const templateUpdater = modifyingCurrentTemplate.mock.calls[0][0]
    expect(templateUpdater({foo: 'bar'})).toEqual({
      foo: 'bar',
      qa_model_template_id: 0,
      id: 'pt-1',
      isTemporary: false,
      isSelected: true,
    })
  })

  test('does not touch project templates when the deleted id is not the currently selected one', async () => {
    const setProjectTemplates = jest.fn()
    const modifyingCurrentTemplate = jest.fn()
    const projectTemplates = [
      {
        id: 'pt-1',
        isTemporary: false,
        isSelected: true,
        qa_model_template_id: 999,
      },
    ]
    const deleteApi = jest.fn(() => Promise.resolve({id: 5}))
    renderMenu({
      settingsPanel: {
        projectTemplates,
        setProjectTemplates,
        modifyingCurrentTemplate,
      },
      subTemplates: {
        currentTemplate: {id: 5, name: 'Foo'},
        propConnectProjectTemplate: SCHEMA_KEYS.qaModelTemplateId,
        deleteApi,
      },
    })

    await act(async () => {
      fireEvent.click(screen.getByTestId('delete-template'))
    })

    expect(setProjectTemplates).not.toHaveBeenCalled()
    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
