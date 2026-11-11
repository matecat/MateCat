jest.mock('../../../../../api/importMemoryGlossary/importMemoryGlossary')
jest.mock('../../../../../api/updateMemoryGlossary/updateMemoryGlossary')
jest.mock('../../../../../actions/CatToolActions')

import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {MTGlossaryRow} from './MTGlossaryRow'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {importMemoryGlossary} from '../../../../../api/importMemoryGlossary/importMemoryGlossary'
import {updateMemoryGlossary} from '../../../../../api/updateMemoryGlossary/updateMemoryGlossary'
import CatToolActions from '../../../../../actions/CatToolActions'

beforeEach(() => {
  jest.clearAllMocks()
})

const file = new File(['content'], 'glossary.xlsx', {
  type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
})

const baseRow = {id: 5, name: 'My glossary', isActive: true}

const Wrapper = ({
  engineId = 1,
  row = baseRow,
  setRows = jest.fn(),
  isReadOnly = false,
  deleteGlossaryConfirm = jest.fn(),
}) => (
  <SettingsPanelContext.Provider value={{}}>
    <MTGlossaryRow
      engineId={engineId}
      row={row}
      setRows={setRows}
      isReadOnly={isReadOnly}
      deleteGlossaryConfirm={deleteGlossaryConfirm}
    />
  </SettingsPanelContext.Provider>
)

// Before editing (non-read-only), the edit pencil is the only button
// rendered ahead of the import/delete controls, so it's always first.
const getEditButton = () => screen.getAllByRole('button')[0]

test('renders the glossary name and active state', () => {
  render(<Wrapper />)

  expect(screen.getByDisplayValue('My glossary')).toBeInTheDocument()
  expect(screen.getByTestId('mtglossary-active-5')).toBeChecked()
})

test('toggling the active checkbox updates rows via setRows', async () => {
  const user = userEvent.setup()
  const setRows = jest.fn()
  render(<Wrapper setRows={setRows} />)

  await user.click(screen.getByTestId('mtglossary-active-5'))

  expect(setRows).toHaveBeenCalled()
  const updater = setRows.mock.calls[0][0]
  const result = updater([{id: 5, isActive: true}, {id: 6, isActive: false}])
  expect(result).toEqual([
    {id: 5, isActive: false},
    {id: 6, isActive: false},
  ])
})

test('read-only rows hide the edit, import and delete controls', () => {
  render(<Wrapper isReadOnly />)

  expect(screen.queryByText('Update')).not.toBeInTheDocument()
  expect(
    screen.queryByTestId('delete-mtglossary-5'),
  ).not.toBeInTheDocument()
})

test('clicking the delete button calls deleteGlossaryConfirm with the row', async () => {
  const user = userEvent.setup()
  const deleteGlossaryConfirm = jest.fn()
  render(<Wrapper deleteGlossaryConfirm={deleteGlossaryConfirm} />)

  await user.click(screen.getByTestId('delete-mtglossary-5'))

  expect(deleteGlossaryConfirm).toHaveBeenCalledWith(baseRow)
})

describe('renaming', () => {
  test('entering edit mode shows the Confirm/Close buttons with the current name', async () => {
    const user = userEvent.setup()
    render(<Wrapper />)

    await user.click(getEditButton())

    expect(screen.getByText('Confirm')).toBeInTheDocument()
    expect(screen.getByDisplayValue('My glossary')).toBeEnabled()
  })

  test('confirming a rename calls updateMemoryGlossary and updates rows on success', async () => {
    const user = userEvent.setup()
    const setRows = jest.fn()
    updateMemoryGlossary.mockResolvedValue({name: 'Renamed glossary'})

    render(<Wrapper engineId={9} setRows={setRows} />)

    await user.click(getEditButton())
    const nameInput = screen.getByDisplayValue('My glossary')
    await user.clear(nameInput)
    await user.type(nameInput, 'Renamed glossary')

    await user.click(screen.getByText('Confirm'))

    expect(updateMemoryGlossary).toHaveBeenCalledWith({
      engineId: 9,
      memoryId: 5,
      name: 'Renamed glossary',
    })
    await waitFor(() => expect(setRows).toHaveBeenCalled())

    const updater = setRows.mock.calls[0][0]
    const result = updater([{id: 5, name: 'My glossary'}, {id: 6, name: 'Other'}])
    expect(result).toEqual([
      {id: 5, name: 'Renamed glossary'},
      {id: 6, name: 'Other'},
    ])

    await waitFor(() =>
      expect(screen.queryByText('Confirm')).not.toBeInTheDocument(),
    )
  })

  test('confirming a rename that fails resets the name but stays in edit mode', async () => {
    const user = userEvent.setup()
    updateMemoryGlossary.mockRejectedValue(new Error('server error'))

    render(<Wrapper />)

    await user.click(getEditButton())
    const nameInput = screen.getByDisplayValue('My glossary')
    await user.clear(nameInput)
    await user.type(nameInput, 'Broken rename')

    await user.click(screen.getByText('Confirm'))

    expect(await screen.findByDisplayValue('My glossary')).toBeInTheDocument()
    expect(screen.getByText('Confirm')).toBeInTheDocument()
  })

  test('closing the edit without confirming resets the name and exits edit mode', async () => {
    const user = userEvent.setup()
    render(<Wrapper />)

    await user.click(getEditButton())
    const nameInput = screen.getByDisplayValue('My glossary')
    await user.clear(nameInput)
    await user.type(nameInput, 'Discarded')

    // While editing, buttons are [Confirm, Close (icon-only), Delete]; Close
    // has no accessible name of its own, so it's selected by position.
    const closeButton = screen.getAllByRole('button')[1]
    await user.click(closeButton)

    expect(screen.queryByText('Confirm')).not.toBeInTheDocument()
    expect(screen.getByDisplayValue('My glossary')).toBeInTheDocument()
  })
})

describe('importing a glossary file', () => {
  test('selecting a file imports it and shows a success notification on immediate completion', async () => {
    importMemoryGlossary.mockResolvedValue({progress: 1})

    const user = userEvent.setup()
    render(<Wrapper engineId={3} />)

    await user.upload(screen.getByLabelText(/update/i), file)

    expect(importMemoryGlossary).toHaveBeenCalledWith({
      engineId: 3,
      glossary: file,
      memoryId: 5,
    })
    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Glossary imported',
          type: 'success',
        }),
      ),
    )
  })

  test('shows an error notification when the import API rejects', async () => {
    importMemoryGlossary.mockRejectedValue({errors: [{message: 'bad file'}]})

    const user = userEvent.setup()
    render(<Wrapper />)

    await user.upload(screen.getByLabelText(/update/i), file)

    await waitFor(() =>
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Glossary import error',
          type: 'error',
        }),
      ),
    )
  })
})
