import React, {useEffect, useRef} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {QF_SCHEMA_KEYS, QualityFrameworkTab} from './QualityFrameworkTab'
import {mswServer} from '../../../../../../../mocks/mswServer'
import {HttpResponse, http} from 'msw'
import qaModelTemplateMocks from '../../../../../../../mocks/qaModelTemplateMocks'
import projectTemplateMock from '../../../../../../../mocks/projectTemplateMock'
import {
  act,
  render,
  renderHook,
  screen,
  waitFor,
  within,
} from '@testing-library/react'
import useTemplates from '../../../../hooks/useTemplates'
import userEvent from '@testing-library/user-event'
import {getCategoryLabelAndDescription} from './CategoriesSeveritiesTable'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
  isLoggedIn: 1,
}

const wrapperElement = document.createElement('div')
const WrapperComponent = (contextProps) => {
  const ref = useRef()

  useEffect(() => {
    ref.current.appendChild(wrapperElement)
  }, [])

  return (
    <SettingsPanelContext.Provider
      value={{...contextProps, portalTarget: wrapperElement}}
    >
      <div ref={ref}>
        <QualityFrameworkTab />
      </div>
    </SettingsPanelContext.Provider>
  )
}

beforeEach(() => {
  mswServer.use(
    http.get(`${config.basepath}api/v3/qa_model_template`, () => {
      return HttpResponse.json(qaModelTemplateMocks)
    }),
  )
})

test('Render properly and change ept thresholds', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }

  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const R1Input = screen.getByTestId('threshold-R1')

  expect(R1Input.value).toBe('20')
  expect(screen.getByTestId('threshold-R2').value).toBe('15')

  await user.click(R1Input)

  await act(async () => user.keyboard('3'))
  refresh()
  await act(async () => user.keyboard('1'))
  refresh()

  expect(screen.getByTestId('save-as-new-template')).toBeInTheDocument()

  refresh()

  expect(R1Input.value).toBe('31')
})

test('Change template', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }

  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  await user.click(screen.getByText('Default'))

  const templateDropDownItem = screen.getByText('QF T1')
  expect(templateDropDownItem).toBeInTheDocument()

  await user.click(templateDropDownItem)
  refresh()

  const R1Input = screen.getByTestId('threshold-R1')

  expect(R1Input.value).toBe('28')
  expect(screen.getByTestId('threshold-R2').value).toBe('2')

  await user.click(R1Input)

  await act(async () => user.keyboard('1'))
  refresh()
  await act(async () => user.keyboard('2'))
  refresh()

  expect(R1Input.value).toBe('12')
  expect(R1Input).toHaveClass('quality-framework-not-saved')

  const saveAsChanges = screen.getByTestId('save-as-changes')
  expect(saveAsChanges).toBeInTheDocument()
  expect(screen.getByTestId('save-as-new-template')).toBeInTheDocument()

  mswServer.use(
    http.put(`${config.basepath}api/v3/qa_model_template/:id`, () => {
      const {isTemporary, ...data} = result.current.currentTemplate //eslint-disable-line
      return HttpResponse.json(data)
    }),
  )

  await user.click(saveAsChanges)
  refresh()

  expect(R1Input).not.toHaveClass('quality-framework-not-saved')
})

test('QF template id not exits and select Standard template', async () => {
  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = {
    ...projectTemplateMock.items[1],
    qa_model_template_id: 99,
  }

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  expect(screen.getByText('Default')).toBeInTheDocument()
})

test('Add category', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const addCategory = screen.getByTestId('qf-add-category')
  const addCategoryContainer = within(addCategory)

  await user.click(addCategoryContainer.getByText('Add category'))

  expect(addCategoryContainer.getByTestId('popover')).toBeInTheDocument()

  const nameInput = addCategoryContainer.getByPlaceholderText('Name')
  const descriptionButton = addCategoryContainer.getByText('Add description')

  await user.click(descriptionButton)

  const descriptionInput =
    addCategoryContainer.getByPlaceholderText('Description')

  await user.type(nameInput, 'New category')
  await user.type(descriptionInput, 'Test description')

  await user.click(addCategoryContainer.getByText('Confirm'))
  refresh()

  const categoryRow = within(screen.getByTestId('qf-category-row-6'))

  expect(categoryRow.getByText('New category')).toBeInTheDocument()
  expect(categoryRow.getByText('(Test description)')).toBeInTheDocument()
  expect(screen.getByTestId('qf-category-row-6')).toHaveClass(
    'quality-framework-not-saved',
  )
})

test('Category rename', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const categoryRow = within(screen.getByTestId('qf-category-row-2'))
  const menuButtonShowItems = categoryRow.getByTestId('menu-button-show-items')
  expect(menuButtonShowItems).toBeInTheDocument()

  await user.click(menuButtonShowItems)

  const renameButton = screen.getByTestId('menu-button-rename')

  await user.click(renameButton)

  const modifyCategory = within(screen.getByTestId('qf-modify-category'))
  const nameInput = modifyCategory.getByPlaceholderText('Name')
  const descriptionInput = modifyCategory.getByPlaceholderText('Description')

  const {label, description} = getCategoryLabelAndDescription(
    result.current.currentTemplate.categories[1],
  )

  expect(nameInput.value).toBe(label)
  expect(descriptionInput.value).toBe(description)

  await user.click(nameInput)

  await user.type(nameInput, ' Mod')

  await user.click(modifyCategory.getByText('Confirm'))
  refresh()

  expect(categoryRow.getByText(`${label} Mod`)).toBeInTheDocument()
  expect(screen.getByTestId('qf-category-row-2')).toHaveClass(
    'quality-framework-not-saved',
  )
})

test('Category moveup and movedown', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  let categoryRow = within(screen.getByTestId('qf-category-row-2'))
  let menuButtonShowItems = categoryRow.getByTestId('menu-button-show-items')
  expect(menuButtonShowItems).toBeInTheDocument()

  await user.click(menuButtonShowItems)

  const moveUpButton = screen.getByTestId('menu-button-moveup')

  // moveup
  await user.click(moveUpButton)
  refresh()

  expect(
    contextProps.qualityFrameworkTemplates.currentTemplate.categories[0].label,
  ).toBe('Tag issues (mismatches, whitespaces)')
  expect(
    contextProps.qualityFrameworkTemplates.currentTemplate.categories[1].label,
  ).toBe('Style (readability, consistent style and tone)')

  categoryRow = within(screen.getByTestId('qf-category-row-2'))
  menuButtonShowItems = categoryRow.getByTestId('menu-button-show-items')

  // movedown
  await user.click(menuButtonShowItems)

  expect(screen.getByTestId('menu-button-moveup')).toBeDisabled()

  const moveDownButton = screen.getByTestId('menu-button-movedown')
  await user.click(moveDownButton)
  refresh()

  expect(
    contextProps.qualityFrameworkTemplates.currentTemplate.categories[0].label,
  ).toBe('Style (readability, consistent style and tone)')
  expect(
    contextProps.qualityFrameworkTemplates.currentTemplate.categories[1].label,
  ).toBe('Tag issues (mismatches, whitespaces)')
})

test('Category delete', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const categoryRow = within(screen.getByTestId('qf-category-row-2'))
  const menuButtonShowItems = categoryRow.getByTestId('menu-button-show-items')
  expect(menuButtonShowItems).toBeInTheDocument()

  await user.click(menuButtonShowItems)

  const deleteButton = screen.getByTestId('menu-button-delete')

  await user.click(deleteButton)
  refresh()

  expect(screen.queryByTestId('qf-category-row-2')).not.toBeInTheDocument()
})

test('Add severity', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const addSeverity = screen.getByTestId('qf-add-severity')
  const addSeverityContainer = within(addSeverity)

  await user.click(addSeverityContainer.getByTestId('add-severity-button'))

  expect(addSeverityContainer.getByTestId('popover')).toBeInTheDocument()

  const nameInput = addSeverityContainer.getByPlaceholderText('Name')

  await user.type(nameInput, 'New severity')

  await user.click(addSeverityContainer.getByText('Confirm'))
  refresh()

  const severityColumn = within(screen.getByTestId('qf-severity-column-3'))

  expect(severityColumn.getByText('New severity')).toBeInTheDocument()
  expect(screen.getByTestId('qf-severity-column-3')).toHaveClass(
    'quality-framework-not-saved',
  )
})

test('Edit severity', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const severityCurrentValue =
    result.current.currentTemplate.categories[1].severities[1].penalty.toString()
  const severityCell = within(screen.getByTestId('qf-severity-cell-2-5'))
  const severityInput = severityCell.getByDisplayValue(severityCurrentValue)

  await user.click(severityInput)
  await act(async () => user.keyboard('3'))

  await user.click(screen.getByTestId('qf-category-row-2'))
  refresh()

  expect(screen.getByTestId('qf-severity-cell-2-5')).toHaveClass(
    'cell-not-saved',
  )
})

test('Severity column rename', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const severityColumn = within(screen.getByTestId('qf-severity-column-1'))

  const menuButtonShowItems = severityColumn.getByTestId(
    'menu-button-show-items',
  )
  expect(menuButtonShowItems).toBeInTheDocument()

  await user.click(menuButtonShowItems)

  const renameButton = screen.getByTestId('menu-button-rename')

  await user.click(renameButton)

  const renameSeverity = screen.getByTestId('qf-modify-severity')
  const renameSeverityContainer = within(renameSeverity)

  const nameInput = renameSeverityContainer.getByPlaceholderText('Name')

  await user.type(nameInput, ' copy')

  await user.click(renameSeverityContainer.getByText('Confirm'))
  refresh()

  expect(result.current.currentTemplate.categories[0].severities[1].label).toBe(
    'Minor copy',
  )
})

test('Severity column move left and right', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  let severityColumn = within(screen.getByTestId('qf-severity-column-1'))
  let menuButtonShowItems = severityColumn.getByTestId('menu-button-show-items')
  expect(menuButtonShowItems).toBeInTheDocument()

  await user.click(menuButtonShowItems)

  const moveLeftButton = screen.getByTestId('menu-button-moveleft')

  await user.click(moveLeftButton)
  refresh()

  let {severities} = result.current.currentTemplate.categories[0]

  expect(severities[0].label).toBe('Minor')
  expect(severities[1].label).toBe('Neutral')

  severityColumn = within(screen.getByTestId('qf-severity-column-0'))
  menuButtonShowItems = severityColumn.getByTestId('menu-button-show-items')
  await user.click(menuButtonShowItems)

  expect(screen.getByTestId('menu-button-moveleft')).toBeDisabled()

  const moveRightButton = screen.getByTestId('menu-button-moveright')

  await user.click(moveRightButton)
  refresh()

  severities = result.current.currentTemplate.categories[0].severities

  expect(severities[0].label).toBe('Neutral')
  expect(severities[1].label).toBe('Minor')
})

test('Severity delete', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }
  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const severityColumn = within(screen.getByTestId('qf-severity-column-1'))
  const menuButtonShowItems = severityColumn.getByTestId(
    'menu-button-show-items',
  )
  expect(menuButtonShowItems).toBeInTheDocument()

  await user.click(menuButtonShowItems)

  const deleteButton = screen.getByTestId('menu-button-delete')

  await user.click(deleteButton)
  refresh()

  const column1 = within(screen.queryByTestId('qf-severity-column-1'))
  expect(column1.queryByText('Minor')).not.toBeInTheDocument()
})
