jest.mock('../../../../api/shareTmKey')
jest.mock('../../../../api/getInfoTmKey')
jest.mock('../../../../actions/CatToolActions')
jest.mock('../../../../actions/ModalsActions')
jest.mock('../../../../stores/UserStore')

import React from 'react'
import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {ShareResource} from './ShareResource'
import {shareTmKey} from '../../../../api/shareTmKey'
import {getInfoTmKey} from '../../../../api/getInfoTmKey'
import CatToolActions from '../../../../actions/CatToolActions'
import ModalsActions from '../../../../actions/ModalsActions'
import UserStore from '../../../../stores/UserStore'

const row = {key: 'the-key', name: 'the-name'}

global.config = {
  basepath: 'http://localhost/',
  userMail: 'me@example.com',
}

beforeEach(() => {
  jest.clearAllMocks()
  UserStore.getUser.mockReturnValue({user: {uid: 1}})
  getInfoTmKey.mockResolvedValue({data: []})
})

test('renders the default sharing message when the resource has no other owners', async () => {
  render(<ShareResource row={row} onClose={jest.fn()} onShare={jest.fn()} />)

  await act(async () => Promise.resolve())

  expect(
    screen.getByText(/Share ownership of the resource by sharing the key/),
  ).toBeInTheDocument()
})

test('submitting an invalid email shows a notification and disables the submit button', async () => {
  const user = userEvent.setup()
  render(<ShareResource row={row} onClose={jest.fn()} onShare={jest.fn()} />)
  await act(async () => Promise.resolve())

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  await act(async () => user.type(input, 'not-an-email'))
  await act(async () => user.click(screen.getByText('Share')))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Error sharing resource',
      text: 'The email not-an-email is not valid.',
    }),
  )
  expect(screen.getByText('Share')).toBeDisabled()
  expect(shareTmKey).not.toHaveBeenCalled()
})

test('submitting a valid email shares the resource and notifies success', async () => {
  const user = userEvent.setup()
  const onClose = jest.fn()
  const onShare = jest.fn()
  shareTmKey.mockResolvedValue({})

  render(<ShareResource row={row} onClose={onClose} onShare={onShare} />)
  await act(async () => Promise.resolve())

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  await act(async () => user.type(input, 'valid@example.com'))
  await act(async () => user.click(screen.getByText('Share')))

  expect(shareTmKey).toHaveBeenCalledWith({
    key: row.key,
    description: row.name,
    emails: 'valid@example.com',
  })
  expect(onClose).toHaveBeenCalled()
  expect(onShare).toHaveBeenCalled()
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Resource shared'}),
  )
})

test('shows an API error message when sharing fails', async () => {
  const user = userEvent.setup()
  shareTmKey.mockRejectedValue([{message: 'Cannot share with yourself'}])

  render(<ShareResource row={row} onClose={jest.fn()} onShare={jest.fn()} />)
  await act(async () => Promise.resolve())

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  await act(async () => user.type(input, 'valid@example.com'))
  await act(async () => user.click(screen.getByText('Share')))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      title: 'Error sharing resource',
      text: 'Cannot share with yourself',
    }),
  )
})

test('falls back to a generic error message when sharing fails without a payload', async () => {
  const user = userEvent.setup()
  shareTmKey.mockRejectedValue(undefined)

  render(<ShareResource row={row} onClose={jest.fn()} onShare={jest.fn()} />)
  await act(async () => Promise.resolve())

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  await act(async () => user.type(input, 'valid@example.com'))
  await act(async () => user.click(screen.getByText('Share')))

  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({
      text: 'There was a problem sharing the key, try again or contact the support.',
    }),
  )
})

test('resetting the form restores the default email and closes', async () => {
  const user = userEvent.setup()
  const onClose = jest.fn()

  const {container} = render(
    <ShareResource row={row} onClose={onClose} onShare={jest.fn()} />,
  )
  await act(async () => Promise.resolve())

  const resetButton = container.querySelector('button[type="reset"]')
  await act(async () => user.click(resetButton))

  expect(onClose).toHaveBeenCalledTimes(1)
})

test('shows co-owners and opens the share modal when the resource is already shared', async () => {
  const user = userEvent.setup()
  getInfoTmKey.mockResolvedValue({
    data: [
      {uid: '1', first_name: 'Me', last_name: 'Owner'},
      {uid: '2', first_name: 'Jane', last_name: 'Doe'},
      {uid: '3', first_name: 'John', last_name: 'Smith'},
    ],
  })

  render(<ShareResource row={row} onClose={jest.fn()} onShare={jest.fn()} />)
  await act(async () => Promise.resolve())

  expect(
    screen.getByText(/Shared resource is co-owned by you/),
  ).toBeInTheDocument()
  const emailSpan = screen.getByText(/Jane Doe/)
  expect(emailSpan).toHaveTextContent('and 1 others')

  await act(async () => user.click(emailSpan))

  expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
    expect.anything(),
    expect.objectContaining({
      description: row.name,
      tmKey: row.key,
      user: {uid: 1},
    }),
    'Share resource',
  )
})
