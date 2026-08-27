import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import ShareTmModal from './ShareTmModal'
import {shareTmKey} from '../../api/shareTmKey'
import CatToolActions from '../../actions/CatToolActions'

jest.mock('../../api/shareTmKey')
jest.mock('../../actions/CatToolActions')

const user = {
  uid: 1,
  first_name: 'John',
  last_name: 'Doe',
  email: 'john@doe.com',
}
const users = [
  {uid: 2, first_name: 'Jane', last_name: 'Smith', email: 'jane@smith.com'},
]

afterEach(() => jest.clearAllMocks())

test('renders the owners list including the current user', () => {
  render(
    <ShareTmModal
      description="My TM"
      tmKey="abc123"
      user={user}
      users={users}
      callback={jest.fn()}
      onClose={jest.fn()}
    />,
  )

  expect(screen.getByText(/John Doe\(you\)/)).toBeInTheDocument()
  expect(screen.getByText('Jane Smith')).toBeInTheDocument()
  expect(screen.getByText(/My TM/)).toBeInTheDocument()
  expect(screen.getByText('abc123')).toBeInTheDocument()
})

test('shows a validation error for an invalid email and does not call the API', () => {
  render(
    <ShareTmModal
      description="My TM"
      tmKey="abc123"
      user={user}
      users={users}
      callback={jest.fn()}
      onClose={jest.fn()}
    />,
  )

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  fireEvent.change(input, {target: {value: 'not-an-email'}})
  fireEvent.click(screen.getByText('Share'))

  expect(screen.getByText(/is not valid/)).toBeInTheDocument()
  expect(shareTmKey).not.toHaveBeenCalled()
})

test('sharing with valid emails calls the API, notifies success and closes the modal', async () => {
  shareTmKey.mockResolvedValue()
  const callback = jest.fn()
  const onClose = jest.fn()
  render(
    <ShareTmModal
      description="My TM"
      tmKey="abc123"
      user={user}
      users={users}
      callback={callback}
      onClose={onClose}
    />,
  )

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  fireEvent.change(input, {target: {value: 'valid@example.com'}})
  fireEvent.click(screen.getByText('Share'))

  expect(shareTmKey).toHaveBeenCalledWith({
    key: 'abc123',
    description: 'My TM',
    emails: 'valid@example.com',
  })

  await waitFor(() => expect(callback).toHaveBeenCalledTimes(1))
  expect(onClose).toHaveBeenCalledTimes(1)
  expect(CatToolActions.addNotification).toHaveBeenCalledWith(
    expect.objectContaining({title: 'Resource shared', type: 'success'}),
  )
})

test('pressing Enter triggers the share action', () => {
  shareTmKey.mockResolvedValue()
  render(
    <ShareTmModal
      description="My TM"
      tmKey="abc123"
      user={user}
      users={users}
      callback={jest.fn()}
      onClose={jest.fn()}
    />,
  )

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  fireEvent.change(input, {target: {value: 'valid@example.com'}})
  fireEvent.keyUp(input, {key: 'Enter'})

  expect(shareTmKey).toHaveBeenCalled()
})

test('shows an API error message when sharing fails', async () => {
  shareTmKey.mockRejectedValue([{message: 'Sharing failed'}])
  render(
    <ShareTmModal
      description="My TM"
      tmKey="abc123"
      user={user}
      users={users}
      callback={jest.fn()}
      onClose={jest.fn()}
    />,
  )

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  fireEvent.change(input, {target: {value: 'valid@example.com'}})
  fireEvent.click(screen.getByText('Share'))

  await waitFor(() =>
    expect(screen.getByText('Sharing failed')).toBeInTheDocument(),
  )
})

test('typing after an error clears the previous error state', () => {
  render(
    <ShareTmModal
      description="My TM"
      tmKey="abc123"
      user={user}
      users={users}
      callback={jest.fn()}
      onClose={jest.fn()}
    />,
  )

  const input = screen.getByPlaceholderText(
    'Enter email addresses separated by comma',
  )
  fireEvent.change(input, {target: {value: 'not-an-email'}})
  fireEvent.click(screen.getByText('Share'))
  expect(screen.getByText(/is not valid/)).toBeInTheDocument()

  fireEvent.keyUp(input, {key: 'a'})
  expect(screen.queryByText(/is not valid/)).not.toBeInTheDocument()
})
