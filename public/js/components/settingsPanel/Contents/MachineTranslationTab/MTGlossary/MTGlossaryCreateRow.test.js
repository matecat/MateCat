jest.mock('../../../../../api/createMemoryAndImportGlossary/createMemoryAndImportGlossary')
jest.mock('../../../../../actions/CatToolActions')

import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {MTGlossaryCreateRow} from './MTGlossaryCreateRow'
import {MT_GLOSSARY_CREATE_ROW_ID} from './MTGlossaryConstants'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {createMemoryAndImportGlossary} from '../../../../../api/createMemoryAndImportGlossary/createMemoryAndImportGlossary'
import CatToolActions from '../../../../../actions/CatToolActions'

beforeEach(() => {
  jest.clearAllMocks()
})

const file = new File(['content'], 'glossary.xlsx', {
  type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
})

const Wrapper = ({engineId = 1, row = {}, setRows = jest.fn()}) => (
  <SettingsPanelContext.Provider value={{}}>
    <MTGlossaryCreateRow engineId={engineId} row={row} setRows={setRows} />
  </SettingsPanelContext.Provider>
)

const fillForm = async (user) => {
  await user.type(screen.getByTestId('mtglossary-create-name'), 'My glossary')
  await user.upload(screen.getByLabelText(/choose file/i), file)
}

test('the confirm button is disabled until both a name and a file are provided', async () => {
  const user = userEvent.setup()
  render(<Wrapper />)

  const confirmButton = screen.getByTestId('mtglossary-create-confirm')
  expect(confirmButton).toBeDisabled()

  await user.type(screen.getByTestId('mtglossary-create-name'), 'My glossary')
  expect(confirmButton).toBeDisabled()

  await user.upload(screen.getByLabelText(/choose file/i), file)
  expect(confirmButton).toBeEnabled()
})

test('submitting without a name or file shows a validation notification and does not call the API', async () => {
  render(<Wrapper />)

  // The Confirm button is disabled while the form is invalid, so it can't be
  // clicked to reach the validation path — submit the form directly instead.
  const form = screen.getByTestId('mtglossary-create-confirm').form
  form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Glossary create error',
      text: 'Name mandatory',
    }),
  )
  expect(createMemoryAndImportGlossary).not.toHaveBeenCalled()
})

test('submitting a valid form that completes immediately (progress 1) creates the glossary and adds the new row', async () => {
  const user = userEvent.setup()
  const setRows = jest.fn()
  createMemoryAndImportGlossary.mockResolvedValue({memory: 42, progress: 1})

  render(<Wrapper engineId={7} row={{isActive: true}} setRows={setRows} />)

  await fillForm(user)
  await user.click(screen.getByTestId('mtglossary-create-confirm'))

  expect(createMemoryAndImportGlossary).toHaveBeenCalledWith({
    engineId: 7,
    glossary: file,
    name: 'My glossary',
  })
  await waitFor(() =>
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Glossary created', type: 'success'}),
    ),
  )

  const updater = setRows.mock.calls[0][0]
  const result = updater([{id: MT_GLOSSARY_CREATE_ROW_ID, isActive: true}])
  expect(result).toEqual([{id: 42, isActive: true, name: 'My glossary'}])
})

test('shows an error notification when the API rejects', async () => {
  const user = userEvent.setup()
  createMemoryAndImportGlossary.mockRejectedValue({
    errors: [{message: 'invalid file'}],
  })

  render(<Wrapper />)

  await fillForm(user)
  await user.click(screen.getByTestId('mtglossary-create-confirm'))

  await waitFor(() =>
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Glossary create error',
        text: 'Error creating glossary',
      }),
    ),
  )
})

test('clicking the reset button removes the create row', async () => {
  const user = userEvent.setup()
  const setRows = jest.fn()
  render(<Wrapper setRows={setRows} />)

  // The icon-only reset button has no accessible name of its own; it's the
  // only other button alongside Confirm before a file has been selected.
  const resetButton = screen
    .getAllByRole('button')
    .find((button) => button !== screen.getByTestId('mtglossary-create-confirm'))

  await user.click(resetButton)

  expect(setRows).toHaveBeenCalled()
  const updater = setRows.mock.calls[0][0]
  const result = updater([{id: MT_GLOSSARY_CREATE_ROW_ID}, {id: 'kept'}])
  expect(result).toEqual([{id: 'kept'}])
})
