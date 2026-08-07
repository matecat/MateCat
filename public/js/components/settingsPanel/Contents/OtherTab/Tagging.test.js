import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {Tagging, taggingTypes} from './Tagging'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'
import SegmentStore from '../../../../stores/SegmentStore'

jest.mock('../../../../actions/SegmentActions', () => ({
  removeAllSegments: jest.fn(),
}))

jest.mock('../../../../actions/CatToolActions', () => ({
  onRender: jest.fn(),
}))

jest.mock('../../../../stores/SegmentStore', () => ({
  getCurrentSegmentId: jest.fn(() => 42),
}))

jest.mock('../../../common/Select', () => ({
  Select: ({options, activeOptions, onToggleOption, onCloseSelect, children}) => (
    <div>
      <div data-testid="active-options">
        {(activeOptions ?? []).map((option) => option?.id).join(',')}
      </div>
      {options?.map((option) => (
        <div key={option.id} data-testid={`option-${option.id}`}>
          <button
            data-testid={`toggle-${option.id}`}
            onClick={() => onToggleOption(option)}
          >
            toggle
          </button>
          {children ? children(option).row : null}
        </div>
      ))}
      <button data-testid="close-select" onClick={() => onCloseSelect()}>
        close
      </button>
    </div>
  ),
}))

const renderTagging = ({
  currentProjectTemplate = {},
  modifyingCurrentTemplate = jest.fn(),
} = {}) =>
  render(
    <CreateProjectContext.Provider value={{SELECT_HEIGHT: 200}}>
      <SettingsPanelContext.Provider
        value={{currentProjectTemplate, modifyingCurrentTemplate}}
      >
        <Tagging />
      </SettingsPanelContext.Provider>
    </CreateProjectContext.Provider>,
  )

const defaultTaggingIds = taggingTypes
  .filter((type) => type.default)
  .map((type) => type.id)

describe('Tagging', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    global.config.is_cattool = false
  })

  test('renders heading and description', () => {
    renderTagging()
    expect(screen.getByText('Tagged syntaxes')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Choose which syntaxes to detect and lock in tags during translation.',
      ),
    ).toBeInTheDocument()
  })

  test('activeOptions is empty when subfilteringHandlers is undefined', () => {
    renderTagging({currentProjectTemplate: {}})
    expect(screen.getByTestId('active-options').textContent).toBe('')
  })

  test('activeOptions falls back to default tagging types when subfilteringHandlers is an empty array', () => {
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: []},
    })
    expect(screen.getByTestId('active-options').textContent).toBe(
      defaultTaggingIds.join(','),
    )
  })

  test('activeOptions maps subfilteringHandlers ids to their tagging types', () => {
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: ['twig']},
    })
    expect(screen.getByTestId('active-options').textContent).toBe('twig')
  })

  test('renders code badges for non-html tagging types', () => {
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: ['twig']},
    })
    const twigOption = screen.getByTestId('option-twig')
    expect(twigOption).toHaveTextContent('{{text}}')
    expect(twigOption).toHaveTextContent('{%text%}')
  })

  test('renders html content for html tagging types', () => {
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: ['sprintf']},
    })
    const sprintfOption = screen.getByTestId('option-sprintf')
    expect(sprintfOption).toHaveTextContent('See guides page')
  })

  test('clicking the html content stops event propagation', () => {
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: ['sprintf']},
    })
    const sprintfOption = screen.getByTestId('option-sprintf')
    const htmlContent = sprintfOption.querySelector('a')
    expect(() => fireEvent.click(htmlContent)).not.toThrow()
  })

  test('toggling off a default option keeps the remaining ones', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: []},
      modifyingCurrentTemplate,
    })

    fireEvent.click(screen.getByTestId('toggle-markup'))

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({foo: 'bar'})
    expect(result.foo).toBe('bar')
    expect(result.subfiltering_handlers).toEqual(
      defaultTaggingIds.filter((id) => id !== 'markup'),
    )
  })

  test('toggling off the only active option sets options to null', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: ['twig']},
      modifyingCurrentTemplate,
    })

    fireEvent.click(screen.getByTestId('toggle-twig'))

    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({})
    expect(result.subfiltering_handlers).toBeNull()
  })

  test('toggling an option back to exactly the default set resets options to an empty array', () => {
    const modifyingCurrentTemplate = jest.fn()
    const nonMarkupDefaults = defaultTaggingIds.filter((id) => id !== 'markup')
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: nonMarkupDefaults},
      modifyingCurrentTemplate,
    })

    fireEvent.click(screen.getByTestId('toggle-markup'))

    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({})
    expect(result.subfiltering_handlers).toEqual([])
  })

  test('closing the select does nothing when config.is_cattool is falsy', () => {
    global.config.is_cattool = false
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: ['twig']},
    })

    fireEvent.click(screen.getByTestId('close-select'))

    expect(SegmentActions.removeAllSegments).not.toHaveBeenCalled()
    expect(CatToolActions.onRender).not.toHaveBeenCalled()
  })

  test('closing the select re-renders the cattool when subfilteringHandlers changed', () => {
    global.config.is_cattool = true
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: ['twig']},
    })

    fireEvent.click(screen.getByTestId('close-select'))

    expect(SegmentActions.removeAllSegments).toHaveBeenCalledTimes(1)
    expect(CatToolActions.onRender).toHaveBeenCalledWith({
      segmentToOpen: 42,
    })
  })

  test('closing the select a second time without changes does not re-render again', () => {
    global.config.is_cattool = true
    renderTagging({
      currentProjectTemplate: {subfilteringHandlers: ['twig']},
    })

    fireEvent.click(screen.getByTestId('close-select'))
    fireEvent.click(screen.getByTestId('close-select'))

    expect(SegmentActions.removeAllSegments).toHaveBeenCalledTimes(1)
    expect(CatToolActions.onRender).toHaveBeenCalledTimes(1)
    expect(SegmentStore.getCurrentSegmentId).toHaveBeenCalledTimes(1)
  })
})
