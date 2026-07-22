import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {CreateTeam} from './CreateTeam'
import ManageActions from '../../actions/ManageActions'
import ModalsActions from '../../actions/ModalsActions'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('../../actions/ManageActions')
jest.mock('../../actions/ModalsActions')
jest.mock('../common/EmailsBadge/EmailsBadge', () => ({
  SPECIALS_SEPARATORS: {EnterKey: 'Enter'},
  EmailsBadge: ({onChange}) => (
    <button
      data-testid="emails-badge-mock"
      onClick={() => onChange(['new@member.com'])}
    >
      add emails
    </button>
  ),
}))

const renderCreateTeam = (userInfo) =>
  render(
    <ApplicationWrapperContext.Provider value={{userInfo}}>
      <CreateTeam />
    </ApplicationWrapperContext.Provider>,
  )

afterEach(() => jest.clearAllMocks())

test('the Create button is disabled until a name and valid emails are provided', () => {
  renderCreateTeam({
    user: {first_name: 'John', last_name: 'Doe', email: 'john@doe.com'},
    metadata: null,
  })

  expect(screen.getByText('Create')).toBeDisabled()

  fireEvent.change(screen.getByPlaceholderText('Team name'), {
    target: {value: 'My Team'},
  })
  expect(screen.getByText('Create')).toBeDisabled()

  fireEvent.click(screen.getByTestId('emails-badge-mock'))
  expect(screen.getByText('Create')).toBeEnabled()
})

test('clicking Create calls ManageActions.createTeam and closes the modal', () => {
  renderCreateTeam({
    user: {first_name: 'John', last_name: 'Doe', email: 'john@doe.com'},
    metadata: null,
  })

  fireEvent.change(screen.getByPlaceholderText('Team name'), {
    target: {value: 'My Team'},
  })
  fireEvent.click(screen.getByTestId('emails-badge-mock'))
  fireEvent.click(screen.getByText('Create'))

  expect(ManageActions.createTeam).toHaveBeenCalledWith('My Team', [
    'new@member.com',
  ])
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})

test('pressing Enter submits when the form is valid', () => {
  const {container} = renderCreateTeam({
    user: {first_name: 'John', last_name: 'Doe', email: 'john@doe.com'},
    metadata: null,
  })

  fireEvent.change(screen.getByPlaceholderText('Team name'), {
    target: {value: 'My Team'},
  })
  fireEvent.click(screen.getByTestId('emails-badge-mock'))

  jest.useFakeTimers()
  fireEvent.keyDown(container.querySelector('.team-modal-create'), {
    key: 'Enter',
  })
  jest.advanceTimersByTime(100)
  jest.useRealTimers()

  expect(ManageActions.createTeam).toHaveBeenCalledWith('My Team', [
    'new@member.com',
  ])
})

test('shows the current user name and short name avatar when there is no gplus metadata', () => {
  renderCreateTeam({
    user: {first_name: 'John', last_name: 'Doe', email: 'john@doe.com'},
    metadata: null,
  })

  expect(screen.getByText('John Doe')).toBeInTheDocument()
  expect(screen.getByText('john@doe.com')).toBeInTheDocument()
})

test('shows the gplus avatar image when metadata is present', () => {
  const {container} = renderCreateTeam({
    user: {first_name: 'John', last_name: 'Doe', email: 'john@doe.com'},
    metadata: {gplus_picture: 'pic.png'},
  })

  expect(container.querySelector('img')).toHaveAttribute('src', 'pic.png')
})
