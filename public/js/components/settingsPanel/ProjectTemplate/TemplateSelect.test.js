import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {TemplateSelect} from './TemplateSelect'
import {ApplicationWrapperContext} from '../../common/ApplicationWrapper/ApplicationWrapperContext'

const renderTemplateSelect = (props = {}, appOverrides = {isUserLogged: true}) => {
  const defaultProps = {
    projectTemplates: [],
    setProjectTemplates: jest.fn(),
    currentProjectTemplate: undefined,
    label: 'Project template',
    subtemplatesNotSaved: [],
  }

  return render(
    <ApplicationWrapperContext.Provider value={appOverrides}>
      <TemplateSelect {...defaultProps} {...props} />
    </ApplicationWrapperContext.Provider>,
  )
}

const openDropdown = () => fireEvent.click(screen.getByText('Select template'))

// The loading indicator and the default-template pin icon are decorative —
// neither has an accessible role or text of its own — so they can only be
// targeted by selector. Keeping the rule exemption in one place instead of
// scattered across the tests, matching the pattern in Select.test.js.
/* eslint-disable testing-library/no-container, testing-library/no-node-access */
const getLoadingIndicator = (container) =>
  container.querySelector('.project-template-select-loading')
const getDefaultRowIcon = () => document.querySelector('.select-item-default svg')
/* eslint-enable testing-library/no-container, testing-library/no-node-access */

describe('TemplateSelect', () => {
  test('renders the label and the non-temporary project templates as options', () => {
    renderTemplateSelect({
      projectTemplates: [
        {id: 1, name: 'Template One'},
        {id: 2, name: 'Template Two', isTemporary: true},
      ],
    })

    expect(screen.getByText('Project template')).toBeInTheDocument()

    openDropdown()

    expect(screen.getByText('Template One')).toBeInTheDocument()
    expect(screen.queryByText('Template Two')).not.toBeInTheDocument()
  })

  test('selecting an option marks it as selected and the rest as not', () => {
    const setProjectTemplates = jest.fn()
    renderTemplateSelect({
      projectTemplates: [
        {id: 1, name: 'Template One'},
        {id: 2, name: 'Template Two'},
      ],
      setProjectTemplates,
    })

    openDropdown()
    fireEvent.click(screen.getByText('Template Two'))

    expect(setProjectTemplates).toHaveBeenCalled()
    const updater = setProjectTemplates.mock.calls[0][0]
    const result = updater([
      {id: 1, name: 'Template One', isSelected: true},
      {id: 2, name: 'Template Two', isSelected: false},
    ])

    expect(result).toEqual([
      {id: 1, name: 'Template One', isSelected: false},
      {id: 2, name: 'Template Two', isSelected: true},
    ])
  })

  test('is disabled when the user is not logged in', () => {
    renderTemplateSelect(
      {
        projectTemplates: [{id: 1, name: 'Template One'}],
      },
      {isUserLogged: false},
    )

    openDropdown()

    expect(screen.queryByText('Template One')).not.toBeInTheDocument()
  })

  test('marks the current template with an asterisk while a template is being modified', () => {
    renderTemplateSelect({
      projectTemplates: [{id: 1, name: 'Template One', isTemporary: true}],
      currentProjectTemplate: {id: 1, name: 'Template One'},
    })

    expect(screen.getByText('Template One *')).toBeInTheDocument()
  })

  test('pins the default template row', () => {
    renderTemplateSelect({
      projectTemplates: [{id: 1, name: 'Template One', isDefault: true}],
    })

    openDropdown()

    expect(screen.getByText('Template One')).toBeInTheDocument()
    expect(getDefaultRowIcon()).toBeInTheDocument()
  })

  test('shows the loading indicator after a delay when there are no project templates yet', () => {
    jest.useFakeTimers()
    const {container} = renderTemplateSelect({projectTemplates: []})

    expect(getLoadingIndicator(container)).not.toBeInTheDocument()

    act(() => {
      jest.advanceTimersByTime(200)
    })

    expect(getLoadingIndicator(container)).toBeInTheDocument()

    jest.useRealTimers()
  })

  test('does not show the loading indicator in the cattool, even without project templates', () => {
    jest.useFakeTimers()
    const originalIsCattool = global.config.is_cattool
    global.config.is_cattool = true

    const {container} = renderTemplateSelect({projectTemplates: []})

    act(() => {
      jest.advanceTimersByTime(200)
    })

    expect(getLoadingIndicator(container)).not.toBeInTheDocument()

    global.config.is_cattool = originalIsCattool
    jest.useRealTimers()
  })
})
