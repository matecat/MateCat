jest.mock('../../../../../api/createAndImportDeepLGlossary')
jest.mock('../../../../../actions/CatToolActions')

import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {DeepLGlossaryCreateRow} from './DeepLGlossaryCreateRow'
import {DEEPL_GLOSSARY_CREATE_ROW_ID} from './DeepLGlossaryConstants'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {createAndImportDeepLGlossary} from '../../../../../api/createAndImportDeepLGlossary'
import CatToolActions from '../../../../../actions/CatToolActions'

beforeEach(() => {
  jest.clearAllMocks()
})

const file = new File(['content'], 'glossary.xlsx', {
  type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
})

const Wrapper = ({engineId = 1, row = {}, setRows = jest.fn()}) => (
  <SettingsPanelContext.Provider value={{}}>
    <DeepLGlossaryCreateRow engineId={engineId} row={row} setRows={setRows} />
  </SettingsPanelContext.Provider>
)

const fillForm = async (user) => {
  await user.type(
    screen.getByTestId('deeplglossary-create-name'),
    'My glossary',
  )
  await user.upload(screen.getByLabelText(/choose file/i), file)
}

test('the confirm button is disabled until both a name and a file are provided', async () => {
  const user = userEvent.setup()
  render(<Wrapper />)

  const confirmButton = screen.getByTestId('deeplglossary-create-confirm')
  expect(confirmButton).toBeDisabled()

  await user.type(
    screen.getByTestId('deeplglossary-create-name'),
    'My glossary',
  )
  expect(confirmButton).toBeDisabled()

  await user.upload(screen.getByLabelText(/choose file/i), file)
  expect(confirmButton).toBeEnabled()
})

test('submitting without a name or file shows a validation notification and does not call the API', async () => {
  render(<Wrapper />)

  // The Confirm button is disabled while the form is invalid, so it can't be
  // clicked to reach the validation path — submit the form directly instead.
  const form = screen.getByTestId('deeplglossary-create-confirm').form
  form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Glossary create error',
      text: 'Name mandatory',
    }),
  )
  expect(createAndImportDeepLGlossary).not.toHaveBeenCalled()
})

test('submitting a valid form creates the glossary and adds the new row', async () => {
  const user = userEvent.setup()
  const setRows = jest.fn()
  createAndImportDeepLGlossary.mockResolvedValue({glossary_id: 42})

  render(<Wrapper engineId={7} row={{isActive: false}} setRows={setRows} />)

  await fillForm(user)
  await user.click(screen.getByTestId('deeplglossary-create-confirm'))

  expect(createAndImportDeepLGlossary).toHaveBeenCalledWith({
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
  const result = updater([{id: DEEPL_GLOSSARY_CREATE_ROW_ID, isActive: false}])
  expect(result).toEqual([{id: 42, isActive: false, name: 'My glossary'}])
})

test('shows an error notification when the API rejects', async () => {
  const user = userEvent.setup()
  createAndImportDeepLGlossary.mockRejectedValue(new Error('network error'))

  render(<Wrapper />)

  await fillForm(user)
  await user.click(screen.getByTestId('deeplglossary-create-confirm'))

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
    .find((button) => button !== screen.getByTestId('deeplglossary-create-confirm'))

  await user.click(resetButton)

  expect(setRows).toHaveBeenCalled()
  const updater = setRows.mock.calls[0][0]
  const result = updater([
    {id: DEEPL_GLOSSARY_CREATE_ROW_ID},
    {id: 'kept'},
  ])
  expect(result).toEqual([{id: 'kept'}])
})
