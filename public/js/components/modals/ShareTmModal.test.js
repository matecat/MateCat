import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import ShareTmModal from './ShareTmModal'
import {shareTmKey} from '../../api/shareTmKey'
import CatToolActions from '../../actions/CatToolActions'
import CommonUtils from '../../utils/commonUtils'

jest.mock('../../api/shareTmKey')
jest.mock('../../actions/CatToolActions')
jest.mock('../../utils/commonUtils')

const user = {
  uid: 1,
  first_name: 'John',
  last_name: 'Doe',
  email: 'john@doe.com',
}
const users = [
  {uid: 2, first_name: 'Jane', last_name: 'Smith', email: 'jane@smith.com'},
]

beforeEach(() => {
  CommonUtils.validateEmailList.mockReturnValue({result: true, emails: null})
})

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
  CommonUtils.validateEmailList.mockReturnValue({
    result: false,
    emails: 'bad@x',
  })
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
  fireEvent.change(input, {target: {value: 'bad@x'}})
  fireEvent.click(screen.getByText('Share'))

  expect(screen.getByText(/is not valid/)).toBeInTheDocument()
  const invalidEmail = screen.getByText('bad@x')
  expect(invalidEmail.tagName).toBe('SPAN')
  expect(invalidEmail).toHaveStyle('font-weight: bold')
  expect(shareTmKey).not.toHaveBeenCalled()
  expect(input).toHaveClass('error')
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

  expect(await screen.findByText('Sharing failed')).toBeInTheDocument()
  expect(input).toHaveClass('error')
  expect(CatToolActions.addNotification).not.toHaveBeenCalled()
  expect(callback).not.toHaveBeenCalled()
  expect(onClose).not.toHaveBeenCalled()
})

test('typing after an error clears the previous error state', () => {
  CommonUtils.validateEmailList.mockReturnValue({
    result: false,
    emails: 'not-an-email',
  })
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
  expect(input).not.toHaveClass('error')

  fireEvent.change(input, {target: {value: 'not-an-email'}})
  fireEvent.click(screen.getByText('Share'))
  expect(screen.getByText(/is not valid/)).toBeInTheDocument()
  expect(input).toHaveClass('error')

  fireEvent.keyUp(input, {key: 'a'})
  expect(screen.queryByText(/is not valid/)).not.toBeInTheDocument()
  expect(input).not.toHaveClass('error')
  expect(shareTmKey).not.toHaveBeenCalled()
})
