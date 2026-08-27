jest.mock('../../../../api/updateTmKey')
jest.mock('../../../../api/deleteTmKey')
jest.mock('../../../../api/getTmKeyEnginesInfo/getTmKeyEnginesInfo')
jest.mock('../../../../api/updateJobKeys')
jest.mock('../../../../api/getInfoTmKey')
jest.mock('../../../../actions/CatToolActions')
jest.mock('../../../../actions/ModalsActions')
jest.mock('../../../../actions/CreateProjectActions')

import React, {useState} from 'react'
import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {TMKeyRow} from './TMKeyRow'
import {SPECIAL_ROWS_ID} from './TranslationMemoryGlossaryTab'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {TranslationMemoryGlossaryTabContext} from './TranslationMemoryGlossaryTabContext'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import {updateTmKey} from '../../../../api/updateTmKey'
import {deleteTmKey} from '../../../../api/deleteTmKey'
import {getTmKeyEnginesInfo} from '../../../../api/getTmKeyEnginesInfo/getTmKeyEnginesInfo'
import {updateJobKeys} from '../../../../api/updateJobKeys'
import {getInfoTmKey} from '../../../../api/getInfoTmKey'
import CatToolActions from '../../../../actions/CatToolActions'
import ModalsActions from '../../../../actions/ModalsActions'
import CreateProjectActions from '../../../../actions/CreateProjectActions'

global.config = {
  basepath: 'http://localhost/',
  ownerIsMe: true,
  is_cattool: false,
}

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

beforeAll(() => {
  window.ResizeObserver = ResizeObserver
})

beforeEach(() => {
  jest.clearAllMocks()
  getTmKeyEnginesInfo.mockResolvedValue([])
  getInfoTmKey.mockResolvedValue({data: []})
})

const baseRow = () => ({
  id: 'abc123',
  key: 'abc123',
  name: 'My TM',
  isActive: true,
  isLocked: false,
  r: true,
  w: true,
  penalty: 0,
  is_shared: false,
})

const Wrapper = ({
  row,
  tmKeys = [row],
  projectTemplates = [],
  currentProjectTemplate = {
    tm: [],
    get_public_matches: true,
    public_tm_penalty: 0,
  },
  setTmKeys = jest.fn(),
  modifyingCurrentTemplate = jest.fn(),
  setSpecialRows = jest.fn(),
  isImportTMXInProgress = false,
}) => {
  const [expanded, setExpanded] = useState(null)
  const onExpandRow = ({shouldExpand, content}) =>
    setExpanded(shouldExpand ? content : null)

  return (
    <SettingsPanelContext.Provider
      value={{
        tmKeys,
        setTmKeys,
        modifyingCurrentTemplate,
        currentProjectTemplate,
        projectTemplates,
      }}
    >
      <CreateProjectContext.Provider value={{isImportTMXInProgress}}>
        <TranslationMemoryGlossaryTabContext.Provider value={{setSpecialRows}}>
          <TMKeyRow row={row} onExpandRow={onExpandRow} />
          {expanded}
        </TranslationMemoryGlossaryTabContext.Provider>
      </CreateProjectContext.Provider>
    </SettingsPanelContext.Provider>
  )
}

test('activating a resource while already at the 10 active resources limit shows a notification and reverts', async () => {
  const user = userEvent.setup()
  const row = {...baseRow(), isActive: false, r: false, w: false}
  const activeFillers = Array.from({length: 10}, (_, index) => ({
    id: `filler-${index}`,
    key: `filler-${index}`,
    isActive: true,
  }))
  const setTmKeys = jest.fn()

  render(<Wrapper row={row} tmKeys={[row, ...activeFillers]} setTmKeys={setTmKeys} />)

  const lookup = screen.getByTestId(`tmkey-lookup-${row.id}`)
  await act(async () => user.click(lookup))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Resource cannot be activated'}),
  )
  expect(setTmKeys).not.toHaveBeenCalled()
  expect(lookup).not.toBeChecked()
})

test('toggling lookup on a normal key updates tmKeys and the current template', async () => {
  const user = userEvent.setup()
  const row = {...baseRow(), isActive: false, r: false, w: false}
  const setTmKeys = jest.fn()
  const modifyingCurrentTemplate = jest.fn()

  render(
    <Wrapper
      row={row}
      tmKeys={[row]}
      setTmKeys={setTmKeys}
      modifyingCurrentTemplate={modifyingCurrentTemplate}
    />,
  )

  const lookup = screen.getByTestId(`tmkey-lookup-${row.id}`)
  await act(async () => user.click(lookup))

  expect(setTmKeys).toHaveBeenCalledWith([
    expect.objectContaining({id: row.id, isActive: true, r: true}),
  ])
  expect(modifyingCurrentTemplate).toHaveBeenCalled()
})

test('editing the name: blocks a duplicated name and reverts to the previous value', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  const otherRow = {...baseRow(), id: 'other', key: 'other', name: 'Existing Name'}
  const setTmKeys = jest.fn()

  render(<Wrapper row={row} tmKeys={[row, otherRow]} setTmKeys={setTmKeys} />)

  const nameInput = screen.getByTestId(`tmkey-row-name-${row.id}`)
  await act(async () => {
    await user.clear(nameInput)
    await user.type(nameInput, 'Existing Name')
  })
  await act(async () => nameInput.blur())

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Duplicated name'}),
  )
  expect(updateTmKey).not.toHaveBeenCalled()
})

test('editing the name: blocks an empty name and reverts', async () => {
  const user = userEvent.setup()
  const row = baseRow()

  render(<Wrapper row={row} />)

  const nameInput = screen.getByTestId(`tmkey-row-name-${row.id}`)
  await act(async () => user.clear(nameInput))
  await act(async () => nameInput.blur())

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Error updating resource'}),
  )
  expect(updateTmKey).not.toHaveBeenCalled()
})

test('editing the name: successfully persists a new unique name on blur', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  updateTmKey.mockResolvedValue({})

  render(<Wrapper row={row} />)

  const nameInput = screen.getByTestId(`tmkey-row-name-${row.id}`)
  await act(async () => {
    await user.clear(nameInput)
    await user.type(nameInput, 'New Name')
  })
  await act(async () => nameInput.blur())

  expect(updateTmKey).toHaveBeenCalledWith({
    key: row.key,
    penalty: row.penalty,
    description: 'New Name',
  })
})

test('editing the name: shows an error notification and reverts when the API rejects', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  updateTmKey.mockRejectedValue({errors: [{message: 'Invalid name'}]})

  render(<Wrapper row={row} />)

  const nameInput = screen.getByTestId(`tmkey-row-name-${row.id}`)
  await act(async () => {
    await user.clear(nameInput)
    await user.type(nameInput, 'New Name')
  })
  await act(async () => nameInput.blur())

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Error updating resource',
      text: 'Invalid name',
    }),
  )
})

test('penalty: clicking "Add penalty" applies a penalty of 1 through updateRow', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  const setTmKeys = jest.fn()

  render(<Wrapper row={row} setTmKeys={setTmKeys} />)

  await act(async () => user.click(screen.getByText('Add penalty')))

  expect(setTmKeys).toHaveBeenCalledWith([
    expect.objectContaining({id: row.id, penalty: 1}),
  ])
})

test('penalty: clicking the reset icon on an existing penalty sets it back to 0', async () => {
  const user = userEvent.setup()
  const row = {...baseRow(), penalty: 5}
  const setTmKeys = jest.fn()

  const {container} = render(<Wrapper row={row} setTmKeys={setTmKeys} />)

  const buttons = container.querySelectorAll(
    '.tm-row-penalty-numeric-stepper button',
  )
  expect(buttons.length).toBe(3)

  await act(async () => user.click(buttons[2]))

  expect(setTmKeys).toHaveBeenCalledWith([
    expect.objectContaining({id: row.id, penalty: 0}),
  ])
})

test('delete: with no other templates involved, expands the DeleteResource row directly', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  getTmKeyEnginesInfo.mockResolvedValue(['MMT'])

  render(<Wrapper row={row} />)

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('delete-resource')))

  expect(getTmKeyEnginesInfo).toHaveBeenCalledWith(row.key)
  expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
  expect(
    screen.getByText(/also linked to your ModernMT account/),
  ).toBeInTheDocument()
})

test('delete: with other templates involved, opens the confirmation modal listing them', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  const involvedTemplate = {id: 5, tm: [{key: row.key}]}
  getTmKeyEnginesInfo.mockResolvedValue(['Lara'])

  render(
    <Wrapper
      row={row}
      projectTemplates={[involvedTemplate, {id: 6, tm: []}]}
    />,
  )

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('delete-resource')))

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    expect.anything(),
    expect.objectContaining({
      projectTemplatesInvolved: [involvedTemplate],
      content:
        'The memory key you are about to delete is used in the following project creation template(s):',
    }),
    'Confirm deletion',
  )
})

test('onConfirmDeleteTmKey: removes the key and notifies on success (non-cattool)', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  const setTmKeys = jest.fn()
  deleteTmKey.mockResolvedValue({})
  getTmKeyEnginesInfo.mockResolvedValue([])

  render(<Wrapper row={row} setTmKeys={setTmKeys} />)

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('delete-resource')))

  const confirmButton = screen.getByText('Confirm')
  await act(async () => user.click(confirmButton))

  expect(deleteTmKey).toHaveBeenCalledWith({key: row.key, removeFrom: ''})
  expect(setTmKeys).toHaveBeenCalled()
  expect(CreateProjectActions.updateProjectTemplates).toHaveBeenCalled()
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Resource deleted'}),
  )
})

test('onConfirmDeleteTmKey: shows an error notification and closes the row on failure', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  deleteTmKey.mockRejectedValue()
  getTmKeyEnginesInfo.mockResolvedValue(['MMT'])

  render(<Wrapper row={row} />)

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('delete-resource')))

  const confirmButton = screen.getByText('Confirm')
  await act(async () => user.click(confirmButton))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Error deleting resource'}),
  )
  expect(screen.queryByText('Confirm')).not.toBeInTheDocument()
})

test('a non-owner key (shared key with *) hides name editing and the options menu', () => {
  const row = {...baseRow(), key: 'abc*123'}

  render(<Wrapper row={row} />)

  expect(screen.getByTestId(`tmkey-row-name-${row.id}`)).toBeDisabled()
  expect(screen.queryByTestId('tm-row-menu')).not.toBeInTheDocument()
})

test('toggling update on a normal active key updates tmKeys accordingly', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  const setTmKeys = jest.fn()

  render(<Wrapper row={row} setTmKeys={setTmKeys} />)

  const update = screen.getByTestId(`tmkey-update-${row.id}`)
  await act(async () => user.click(update))

  expect(setTmKeys).toHaveBeenCalledWith([
    expect.objectContaining({id: row.id, w: false}),
  ])
})

test('the MM shared key row updates specialRows (not tmKeys) when toggled', async () => {
  const user = userEvent.setup()
  const row = {
    ...baseRow(),
    id: SPECIAL_ROWS_ID.defaultTranslationMemory,
    key: '',
  }
  const setTmKeys = jest.fn()
  const setSpecialRows = jest.fn()
  const modifyingCurrentTemplate = jest.fn()

  render(
    <Wrapper
      row={row}
      tmKeys={[]}
      setTmKeys={setTmKeys}
      setSpecialRows={setSpecialRows}
      modifyingCurrentTemplate={modifyingCurrentTemplate}
    />,
  )

  const lookup = screen.getByTestId(`tmkey-lookup-${row.id}`)
  await act(async () => user.click(lookup))

  expect(setTmKeys).not.toHaveBeenCalled()
  expect(setSpecialRows).toHaveBeenCalled()
  expect(modifyingCurrentTemplate).toHaveBeenCalled()
})

test('editing the name in cattool mode also triggers updateJobKeys', async () => {
  const user = userEvent.setup()
  global.config = {...config, is_cattool: true}
  const row = baseRow()
  updateTmKey.mockResolvedValue({})
  updateJobKeys.mockResolvedValue({})

  render(<Wrapper row={row} />)

  const nameInput = screen.getByTestId(`tmkey-row-name-${row.id}`)
  await act(async () => {
    await user.clear(nameInput)
    await user.type(nameInput, 'New Name')
  })
  await act(async () => nameInput.blur())

  expect(updateJobKeys).toHaveBeenCalled()

  global.config = {...config, is_cattool: false}
})

test('onConfirmDeleteTmKey in cattool mode notifies the status change instead of updating templates', async () => {
  const user = userEvent.setup()
  global.config = {...config, is_cattool: true}
  const row = {...baseRow(), isActive: false}
  deleteTmKey.mockResolvedValue({})
  getTmKeyEnginesInfo.mockResolvedValue([])

  render(<Wrapper row={row} />)

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('delete-resource')))

  const confirmButton = screen.getByText('Confirm')
  await act(async () => user.click(confirmButton))

  expect(CatToolActions.onTMKeysChangeStatus).toHaveBeenCalled()
  expect(CreateProjectActions.updateProjectTemplates).not.toHaveBeenCalled()

  global.config = {...config, is_cattool: false}
})

test('delete: when neither MMT nor Lara are linked, no extra footer content is shown', async () => {
  const user = userEvent.setup()
  const row = baseRow()
  getTmKeyEnginesInfo.mockResolvedValue([])

  render(<Wrapper row={row} />)

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('delete-resource')))

  expect(
    screen.queryByText(/also linked to your/),
  ).not.toBeInTheDocument()
  expect(screen.getByText('Confirm')).toBeInTheDocument()
})

test('opening each menu item expands the corresponding row content', async () => {
  const user = userEvent.setup()
  const row = baseRow()

  render(<Wrapper row={row} />)

  await act(async () => user.click(screen.getByTestId('tm-row-import-tmx')))
  expect(screen.getByText('Select a tmx file to import')).toBeInTheDocument()

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('import-glossary')))
  expect(
    screen.getByText('Select termbase in XLSX, XLS or ODS format'),
  ).toBeInTheDocument()

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('export-tmx')))
  expect(
    screen.getByText(
      'We will send a link to download the exported TM to your email.',
    ),
  ).toBeInTheDocument()

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('export-glossary')))
  expect(
    screen.getByText(
      'We will send a link to download the exported termbase to your email.',
    ),
  ).toBeInTheDocument()

  await act(async () => user.click(screen.getByTestId('tm-row-menu')))
  await act(async () => user.click(screen.getByTestId('share-resource')))
  expect(screen.getByText('Share')).toBeInTheDocument()
})

test('the MM shared key row shows the public translation memory icon and hides the key column', () => {
  const row = {
    ...baseRow(),
    id: SPECIAL_ROWS_ID.defaultTranslationMemory,
    key: '',
  }

  render(<Wrapper row={row} />)

  expect(
    screen.queryByText(row.key, {selector: '.tm-key-row-key'}),
  ).not.toBeInTheDocument()
  expect(
    screen.getByTestId(`tmkey-row-name-${row.id}`),
  ).toHaveClass('tm-key-row-name-disabled')
})
