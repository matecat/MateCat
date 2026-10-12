jest.mock('../../../../api/tmCreateRandUser')
jest.mock('../../../../api/createNewTmKey')
jest.mock('../../../../api/checkTMKey')
jest.mock('../../../../api/getInfoTmKey')
jest.mock('../../../../actions/CatToolActions')

import React from 'react'
import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {TMCreateResourceRow} from './TMCreateResourceRow'
import {SPECIAL_ROWS_ID} from './TranslationMemoryGlossaryTabUtils'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {TranslationMemoryGlossaryTabContext} from './TranslationMemoryGlossaryTabContext'
import {tmCreateRandUser} from '../../../../api/tmCreateRandUser'
import {createNewTmKey} from '../../../../api/createNewTmKey'
import {checkTMKey} from '../../../../api/checkTMKey'
import {getInfoTmKey} from '../../../../api/getInfoTmKey'
import CatToolActions from '../../../../actions/CatToolActions'

global.config = {
  basepath: 'http://localhost/',
}

beforeEach(() => {
  jest.clearAllMocks()
  getInfoTmKey.mockResolvedValue({data: []})
})

const Wrapper = ({
  row,
  tmKeys = [],
  setTmKeys = jest.fn(),
  modifyingCurrentTemplate = jest.fn(),
  setSpecialRows = jest.fn(),
}) => (
  <SettingsPanelContext.Provider
    value={{tmKeys, setTmKeys, modifyingCurrentTemplate}}
  >
    <TranslationMemoryGlossaryTabContext.Provider value={{setSpecialRows}}>
      <TMCreateResourceRow row={row} />
    </TranslationMemoryGlossaryTabContext.Provider>
  </SettingsPanelContext.Provider>
)

const newResourceRow = () => ({id: SPECIAL_ROWS_ID.newResource, r: false, w: false})
const sharedResourceRow = () => ({
  id: SPECIAL_ROWS_ID.addSharedResource,
  r: false,
  w: false,
})

test('the name input is focused on mount', () => {
  const row = newResourceRow()
  render(<Wrapper row={row} />)

  expect(screen.getByTestId(row.id)).toHaveFocus()
})

test('the confirm button is disabled until a name is provided for a new resource', async () => {
  const user = userEvent.setup()
  const row = newResourceRow()
  render(<Wrapper row={row} />)

  const confirmButton = screen.getByTestId('create-tmkey-confirm')
  expect(confirmButton).toBeDisabled()

  await act(async () => user.type(screen.getByTestId(row.id), 'a name'))

  expect(confirmButton).toBeEnabled()
})

test('submitting an empty name shows a validation notification and does not create a resource', async () => {
  const user = userEvent.setup()
  const row = newResourceRow()

  render(<Wrapper row={row} />)

  const form = screen.getByTestId('create-tmkey-confirm').closest('form')
  await act(async () => {
    form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}))
  })

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Error adding resource',
      text: 'Resource name cannot be empty. Please provide a valid name.',
    }),
  )
  expect(tmCreateRandUser).not.toHaveBeenCalled()
})

test('submitting a shared resource without a key shows an invalid key notification', async () => {
  const user = userEvent.setup()
  const row = sharedResourceRow()

  render(<Wrapper row={row} />)

  await act(async () => user.type(screen.getByTestId(row.id), 'shared name'))

  const form = screen.getByTestId('create-tmkey-confirm').closest('form')
  await act(async () => {
    form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}))
  })

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Error adding resource',
      text: 'Invalid key',
    }),
  )
})

test('submitting a duplicated name shows a duplicated name notification', async () => {
  const user = userEvent.setup()
  const row = newResourceRow()
  const existing = {name: 'Existing Name'}

  render(<Wrapper row={row} tmKeys={[existing]} />)

  await act(async () =>
    user.type(screen.getByTestId(row.id), 'Existing Name'),
  )

  const form = screen.getByTestId('create-tmkey-confirm').closest('form')
  await act(async () => {
    form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}))
  })

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Duplicated name'}),
  )
  expect(tmCreateRandUser).not.toHaveBeenCalled()
})

test('creating a new resource succeeds: creates the key, updates tmKeys, resets the row', async () => {
  const user = userEvent.setup()
  const row = newResourceRow()
  const setTmKeys = jest.fn()
  const modifyingCurrentTemplate = jest.fn()
  const setSpecialRows = jest.fn()

  tmCreateRandUser.mockResolvedValue({data: {key: 'new-key-123'}})
  createNewTmKey.mockResolvedValue({})

  render(
    <Wrapper
      row={row}
      setTmKeys={setTmKeys}
      modifyingCurrentTemplate={modifyingCurrentTemplate}
      setSpecialRows={setSpecialRows}
    />,
  )

  await act(async () => user.type(screen.getByTestId(row.id), 'My new TM'))
  await act(async () => user.click(screen.getByTestId('create-tmkey-confirm')))

  expect(tmCreateRandUser).toHaveBeenCalled()
  expect(createNewTmKey).toHaveBeenCalledWith({
    key: 'new-key-123',
    description: 'My new TM',
  })
  expect(setTmKeys).toHaveBeenCalledWith([
    expect.objectContaining({key: 'new-key-123', name: 'My new TM'}),
  ])
  expect(modifyingCurrentTemplate).toHaveBeenCalled()
  expect(setSpecialRows).toHaveBeenCalled()
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Resource created '}),
  )
})

test('creating a new resource shows an error notification when the API rejects', async () => {
  const user = userEvent.setup()
  const row = newResourceRow()

  tmCreateRandUser.mockResolvedValue({data: {key: 'new-key-123'}})
  createNewTmKey.mockRejectedValue({errors: [{message: 'Name already used'}]})

  render(<Wrapper row={row} />)

  await act(async () => user.type(screen.getByTestId(row.id), 'My new TM'))
  await act(async () => user.click(screen.getByTestId('create-tmkey-confirm')))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Invalid resource name',
      text: 'Name already used',
    }),
  )
})

test('adding a shared resource succeeds through checkTMKey -> createNewTmKey -> getInfoTmKey', async () => {
  const user = userEvent.setup()
  const row = sharedResourceRow()
  const setTmKeys = jest.fn()

  checkTMKey.mockResolvedValue({success: true})
  createNewTmKey.mockResolvedValue({})
  getInfoTmKey.mockResolvedValue({
    data: [{uid: 1}, {uid: 2}],
  })

  render(<Wrapper row={row} setTmKeys={setTmKeys} />)

  await act(async () => user.type(screen.getByTestId(row.id), 'Shared TM'))
  await act(async () =>
    user.type(screen.getByTestId(`input-${row.id}`), 'shared-key-1'),
  )
  await act(async () => user.click(screen.getByTestId('create-tmkey-confirm')))

  expect(checkTMKey).toHaveBeenCalledWith({tmKey: 'shared-key-1'})
  expect(createNewTmKey).toHaveBeenCalledWith({
    key: 'shared-key-1',
    description: 'Shared TM',
  })
  expect(setTmKeys).toHaveBeenCalledWith([
    expect.objectContaining({key: 'shared-key-1', is_shared: true}),
  ])
})

test('adding a shared resource fails validation when the key is already assigned to an active TM', async () => {
  const user = userEvent.setup()
  const row = sharedResourceRow()
  const assignedKey = {owner: true, key: 'already-used', isActive: true}

  render(<Wrapper row={row} tmKeys={[assignedKey]} />)

  await act(async () => user.type(screen.getByTestId(row.id), 'Shared TM'))
  await act(async () =>
    user.type(screen.getByTestId(`input-${row.id}`), 'already-used'),
  )
  await act(async () => user.click(screen.getByTestId('create-tmkey-confirm')))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Invalid key',
      text: 'The key is already assigned to one of your Active TMs.',
    }),
  )
  expect(checkTMKey).not.toHaveBeenCalled()
})

test('adding a shared resource fails validation when the key is already assigned to an inactive TM', async () => {
  const user = userEvent.setup()
  const row = sharedResourceRow()
  const assignedKey = {owner: true, key: 'already-used', isActive: false}

  render(<Wrapper row={row} tmKeys={[assignedKey]} />)

  await act(async () => user.type(screen.getByTestId(row.id), 'Shared TM'))
  await act(async () =>
    user.type(screen.getByTestId(`input-${row.id}`), 'already-used'),
  )
  await act(async () => user.click(screen.getByTestId('create-tmkey-confirm')))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Invalid key',
      text: 'The key is already assigned to one of your Inactive TMs.',
    }),
  )
})

test('adding a shared resource shows an error notification when checkTMKey rejects', async () => {
  const user = userEvent.setup()
  const row = sharedResourceRow()

  checkTMKey.mockRejectedValue({errors: [{message: 'Server error'}]})

  render(<Wrapper row={row} />)

  await act(async () => user.type(screen.getByTestId(row.id), 'Shared TM'))
  await act(async () =>
    user.type(screen.getByTestId(`input-${row.id}`), 'a-key'),
  )
  await act(async () => user.click(screen.getByTestId('create-tmkey-confirm')))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Invalid key', text: 'Server error'}),
  )
})

test('adding a shared resource shows an error notification when createNewTmKey rejects', async () => {
  const user = userEvent.setup()
  const row = sharedResourceRow()

  checkTMKey.mockResolvedValue({success: true})
  createNewTmKey.mockRejectedValue({errors: [{message: 'Key taken'}]})

  render(<Wrapper row={row} />)

  await act(async () => user.type(screen.getByTestId(row.id), 'Shared TM'))
  await act(async () =>
    user.type(screen.getByTestId(`input-${row.id}`), 'a-key'),
  )
  await act(async () => user.click(screen.getByTestId('create-tmkey-confirm')))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Invalid key', text: 'Key taken'}),
  )
})

test('clicking the reset button removes the special row via setSpecialRows', async () => {
  const user = userEvent.setup()
  const row = newResourceRow()
  const setSpecialRows = jest.fn()

  render(<Wrapper row={row} setSpecialRows={setSpecialRows} />)

  const resetButton = screen
    .getByTestId('create-tmkey-confirm')
    .closest('form')
    .querySelector('button[type="reset"]')

  await act(async () => user.click(resetButton))

  expect(setSpecialRows).toHaveBeenCalled()
  const updater = setSpecialRows.mock.calls[0][0]
  const result = updater([
    {id: SPECIAL_ROWS_ID.newResource},
    {id: SPECIAL_ROWS_ID.addSharedResource},
    {id: 'kept'},
  ])
  expect(result).toEqual([{id: 'kept'}])
})
