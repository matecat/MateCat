import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {SubTemplates} from './SubTemplate'
import CatToolActions from '../../../../actions/CatToolActions'

jest.mock('../../../../actions/CatToolActions', () => ({
  addNotification: jest.fn(),
}))

jest.mock('./SubTemplateSelect', () => ({
  SubTemplateSelect: () => <div data-testid="sub-template-select" />,
}))

jest.mock('./SubTemplateMoreMenu', () => ({
  SubTemplateMoreMenu: () => <div data-testid="sub-template-more-menu" />,
}))

jest.mock('./SubTemplateCreateUpdateControl', () => ({
  SubTemplateCreateUpdateControl: () => (
    <div data-testid="sub-template-create-update-control" />
  ),
}))

jest.mock('./SubTemplateNameInput', () => {
  const actualReact = require('react')
  const {SubTemplatesContext} = require('./SubTemplateContext')
  return {
    SubTemplateNameInput: () => {
      const ctx = actualReact.useContext(SubTemplatesContext)
      return (
        <div data-testid="sub-template-name-input">
          <button
            data-testid="set-name"
            onClick={() => ctx.setTemplateName('New Name')}
          >
            set name
          </button>
          <button
            data-testid="trigger-confirm"
            onClick={() => ctx.updateNameBehaviour.current.confirm()}
          >
            confirm
          </button>
          <button
            data-testid="trigger-cancel"
            onClick={() => ctx.updateNameBehaviour.current.cancel()}
          >
            cancel
          </button>
          <button
            data-testid="trigger-create"
            onClick={() => ctx.createTemplate.current()}
          >
            create
          </button>
        </div>
      )
    },
  }
})

const buildProps = (overrides = {}) => ({
  templates: [{id: 1, name: 'Template One', isSelected: true}],
  setTemplates: jest.fn(),
  currentTemplate: {id: 1, name: 'Template One'},
  modifyingCurrentTemplate: jest.fn(),
  schema: {name: 'name'},
  propConnectProjectTemplate: 'qa_model_template_id',
  getFilteredSchemaCreateUpdate: jest.fn((template) => ({...template})),
  getFilteredSchemaToCompare: jest.fn((template) => template),
  getModalTryingSaveIdenticalSettingsTemplate: jest.fn(() => Promise.resolve()),
  createApi: jest.fn(() => Promise.resolve({id: 2})),
  updateApi: jest.fn(() => Promise.resolve({id: 1, name: 'New Name'})),
  deleteApi: jest.fn(() => Promise.resolve({id: 1})),
  saveErrorCallback: jest.fn(),
  ...overrides,
})

const renderSubTemplate = (overrides = {}) => {
  const props = buildProps(overrides)
  const utils = render(<SubTemplates {...props} />)
  return {...utils, props}
}

describe('SubTemplates', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('renders the select and no name input when not modifying', () => {
    renderSubTemplate()
    expect(screen.getByTestId('sub-template-select')).toBeInTheDocument()
    expect(
      screen.queryByTestId('sub-template-name-input'),
    ).not.toBeInTheDocument()
  })

  test('does not show save buttons or the more menu for the standard template with no temporary drafts', () => {
    renderSubTemplate({
      templates: [{id: 0, name: 'Standard', isSelected: true}],
      currentTemplate: {id: 0, name: 'Standard'},
    })
    expect(screen.queryByTestId('save-as-changes')).not.toBeInTheDocument()
    expect(screen.queryByTestId('save-as-new-template')).not.toBeInTheDocument()
    expect(
      screen.queryByTestId('sub-template-more-menu'),
    ).not.toBeInTheDocument()
  })

  test('shows save-as-changes, save-as-new and the more menu when modifying a non-standard template', () => {
    renderSubTemplate({
      templates: [
        {id: 1, name: 'Template One', isSelected: true},
        {id: 1, name: 'Template One', isTemporary: true},
      ],
      currentTemplate: {id: 1, name: 'Template One'},
    })
    expect(screen.getByTestId('save-as-changes')).toBeInTheDocument()
    expect(screen.getByTestId('save-as-new-template')).toBeInTheDocument()
    expect(screen.getByTestId('sub-template-more-menu')).toBeInTheDocument()
  })

  test('hides save-as-changes but still shows save-as-new for the standard template while modifying', () => {
    renderSubTemplate({
      templates: [
        {id: 0, name: 'Standard', isSelected: true},
        {id: 0, name: 'Standard', isTemporary: true},
      ],
      currentTemplate: {id: 0, name: 'Standard'},
    })
    expect(screen.queryByTestId('save-as-changes')).not.toBeInTheDocument()
    expect(screen.getByTestId('save-as-new-template')).toBeInTheDocument()
    expect(
      screen.queryByTestId('sub-template-more-menu'),
    ).not.toBeInTheDocument()
  })

  test('clicking save-as-new switches to create mode and shows the name input', () => {
    renderSubTemplate({
      templates: [
        {id: 1, name: 'Template One', isSelected: true},
        {id: 1, name: 'Template One', isTemporary: true},
      ],
    })

    fireEvent.click(screen.getByTestId('save-as-new-template'))

    expect(screen.getByTestId('sub-template-name-input')).toBeInTheDocument()
    expect(
      screen.getByTestId('sub-template-create-update-control'),
    ).toBeInTheDocument()
    expect(screen.queryByTestId('save-as-changes')).not.toBeInTheDocument()
  })

  test('cancel resets the template modifier and name, restoring the normal buttons', () => {
    renderSubTemplate({
      templates: [
        {id: 1, name: 'Template One', isSelected: true},
        {id: 1, name: 'Template One', isTemporary: true},
      ],
    })

    fireEvent.click(screen.getByTestId('save-as-new-template'))
    fireEvent.click(screen.getByTestId('trigger-cancel'))

    expect(
      screen.queryByTestId('sub-template-name-input'),
    ).not.toBeInTheDocument()
    expect(screen.getByTestId('save-as-new-template')).toBeInTheDocument()
  })

  test('changing currentTemplate resets the modifier and name', () => {
    const {rerender, props} = renderSubTemplate({
      templates: [
        {id: 1, name: 'Template One', isSelected: true},
        {id: 1, name: 'Template One', isTemporary: true},
      ],
    })

    fireEvent.click(screen.getByTestId('save-as-new-template'))
    expect(screen.getByTestId('sub-template-name-input')).toBeInTheDocument()

    rerender(
      <SubTemplates {...props} currentTemplate={{id: 2, name: 'Other'}} />,
    )

    expect(
      screen.queryByTestId('sub-template-name-input'),
    ).not.toBeInTheDocument()
  })

  describe('createTemplate.current', () => {
    test('shows a duplicated name notification and skips creation when the name already exists', async () => {
      const createApi = jest.fn(() => Promise.resolve({id: 2}))
      renderSubTemplate({
        templates: [
          {id: 1, name: 'New Name', isSelected: true},
          {id: 1, name: 'Draft', isTemporary: true},
        ],
        createApi,
      })

      fireEvent.click(screen.getByTestId('save-as-new-template'))
      fireEvent.click(screen.getByTestId('set-name'))

      await act(async () => {
        fireEvent.click(screen.getByTestId('trigger-create'))
      })

      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Duplicated name'}),
      )
      expect(createApi).not.toHaveBeenCalled()
    })

    test('creates the template and replaces temporary drafts on success', async () => {
      const setTemplates = jest.fn()
      const createApi = jest.fn(() => Promise.resolve({id: 2}))
      renderSubTemplate({
        templates: [
          {id: 1, name: 'Template One', isSelected: true},
          {id: 1, name: 'Template One', isTemporary: true},
        ],
        setTemplates,
        createApi,
      })

      fireEvent.click(screen.getByTestId('save-as-new-template'))
      fireEvent.click(screen.getByTestId('set-name'))

      await act(async () => {
        fireEvent.click(screen.getByTestId('trigger-create'))
      })

      expect(createApi).toHaveBeenCalledTimes(1)
      expect(setTemplates).toHaveBeenCalledTimes(1)
      const updater = setTemplates.mock.calls[0][0]
      const result = updater([
        {id: 1, name: 'Template One', isSelected: true},
        {id: 1, name: 'Template One', isTemporary: true},
      ])
      expect(result).toEqual([
        {id: 1, name: 'Template One', isSelected: false},
        {name: 'New Name', id: 2, isSelected: true},
      ])
    })

    test('skips creation when the identical-settings confirmation is rejected', async () => {
      const createApi = jest.fn(() => Promise.resolve({id: 2}))
      const getModalTryingSaveIdenticalSettingsTemplate = jest.fn(() =>
        Promise.reject(new Error('cancelled')),
      )
      renderSubTemplate({
        templates: [
          {id: 1, name: 'Template One', isSelected: true},
          {id: 1, name: 'Draft', isTemporary: true},
          {id: 3, name: 'Other', isSelected: false},
        ],
        getFilteredSchemaToCompare: jest.fn(() => ({same: true})),
        getModalTryingSaveIdenticalSettingsTemplate,
        createApi,
      })

      fireEvent.click(screen.getByTestId('save-as-new-template'))
      fireEvent.click(screen.getByTestId('set-name'))

      await act(async () => {
        fireEvent.click(screen.getByTestId('trigger-create'))
      })

      expect(getModalTryingSaveIdenticalSettingsTemplate).toHaveBeenCalled()
      expect(createApi).not.toHaveBeenCalled()
    })

    test('calls saveErrorCallback when createApi rejects', async () => {
      const saveErrorCallback = jest.fn()
      const error = new Error('boom')
      const createApi = jest.fn(() => Promise.reject(error))
      renderSubTemplate({
        templates: [
          {id: 1, name: 'Template One', isSelected: true},
          {id: 1, name: 'Draft', isTemporary: true},
        ],
        createApi,
        saveErrorCallback,
      })

      fireEvent.click(screen.getByTestId('save-as-new-template'))
      fireEvent.click(screen.getByTestId('set-name'))

      await act(async () => {
        fireEvent.click(screen.getByTestId('trigger-create'))
      })

      expect(saveErrorCallback).toHaveBeenCalledWith(error)
    })
  })

  describe('updateNameBehaviour.current.confirm', () => {
    test('shows a duplicated name notification when another template already uses the name', () => {
      const updateApi = jest.fn(() => Promise.resolve({id: 1}))
      renderSubTemplate({
        templates: [
          {id: 1, name: 'Template One', isSelected: true},
          {id: 1, name: 'Draft', isTemporary: true},
          {id: 4, name: 'New Name', isSelected: false},
        ],
        updateApi,
      })

      fireEvent.click(screen.getByTestId('save-as-new-template'))
      fireEvent.click(screen.getByTestId('set-name'))
      fireEvent.click(screen.getByTestId('trigger-confirm'))

      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Duplicated name'}),
      )
      expect(updateApi).not.toHaveBeenCalled()
    })

    test('renames the original template on success', async () => {
      const setTemplates = jest.fn()
      const modifyingCurrentTemplate = jest.fn()
      const updateApi = jest.fn(() => Promise.resolve({id: 1}))
      renderSubTemplate({
        templates: [
          {id: 1, name: 'Template One', isSelected: true},
          {id: 1, name: 'Draft', isTemporary: true},
        ],
        currentTemplate: {id: 1, name: 'Template One'},
        setTemplates,
        modifyingCurrentTemplate,
        updateApi,
      })

      fireEvent.click(screen.getByTestId('save-as-new-template'))
      fireEvent.click(screen.getByTestId('set-name'))

      await act(async () => {
        fireEvent.click(screen.getByTestId('trigger-confirm'))
      })

      expect(updateApi).toHaveBeenCalledWith({
        id: 1,
        template: {id: 1, name: 'New Name', isSelected: true},
      })
      expect(setTemplates).toHaveBeenCalledTimes(1)
      const templatesUpdater = setTemplates.mock.calls[0][0]
      expect(
        templatesUpdater([
          {id: 1, name: 'Template One', isSelected: true},
          {id: 2, name: 'Other'},
        ]),
      ).toEqual([
        {id: 1, name: 'New Name', isSelected: true},
        {id: 2, name: 'Other'},
      ])

      expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
      const templateUpdater = modifyingCurrentTemplate.mock.calls[0][0]
      expect(templateUpdater({foo: 'bar'})).toEqual({
        foo: 'bar',
        name: 'New Name',
      })
    })
  })

  describe('updateTemplate (save-as-changes)', () => {
    test('updates the current template and clears request/modifier state', async () => {
      const setTemplates = jest.fn()
      const modifyingCurrentTemplate = jest.fn()
      const updateApi = jest.fn(() =>
        Promise.resolve({id: 1, name: 'Template One'}),
      )
      const getModalTryingSaveIdenticalSettingsTemplate = jest.fn(() =>
        Promise.resolve(),
      )
      renderSubTemplate({
        templates: [
          {id: 1, name: 'Template One', isSelected: true},
          {id: 1, name: 'Template One', isTemporary: true},
          {id: 2, name: 'Other'},
        ],
        currentTemplate: {id: 1, name: 'Template One'},
        setTemplates,
        modifyingCurrentTemplate,
        updateApi,
        getFilteredSchemaToCompare: jest.fn(() => ({same: true})),
        getModalTryingSaveIdenticalSettingsTemplate,
      })

      await act(async () => {
        fireEvent.click(screen.getByTestId('save-as-changes'))
      })

      expect(getModalTryingSaveIdenticalSettingsTemplate).toHaveBeenCalledWith(
        [{id: 2, name: 'Other'}],
      )
      expect(updateApi).toHaveBeenCalledWith({
        id: 1,
        template: {id: 1, name: 'Template One'},
      })
      expect(setTemplates).toHaveBeenCalledTimes(1)
      const templatesUpdater = setTemplates.mock.calls[0][0]
      expect(
        templatesUpdater([
          {id: 1, name: 'Template One', isSelected: false},
          {id: 1, name: 'Template One', isTemporary: true},
          {id: 2, name: 'Other'},
        ]),
      ).toEqual([
        {
          id: 1,
          name: 'Template One',
          isSelected: true,
        },
        {id: 2, name: 'Other'},
      ])
      expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    })

    test('calls saveErrorCallback when updateApi rejects', async () => {
      const saveErrorCallback = jest.fn()
      const error = new Error('boom')
      const updateApi = jest.fn(() => Promise.reject(error))
      renderSubTemplate({
        templates: [
          {id: 1, name: 'Template One', isSelected: true},
          {id: 1, name: 'Template One', isTemporary: true},
        ],
        updateApi,
        saveErrorCallback,
      })

      await act(async () => {
        fireEvent.click(screen.getByTestId('save-as-changes'))
      })

      expect(saveErrorCallback).toHaveBeenCalledWith(error)
    })
  })
})
