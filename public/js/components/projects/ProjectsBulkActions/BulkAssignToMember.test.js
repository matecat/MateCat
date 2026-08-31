import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {BulkAssignToMember} from './BulkAssignToMember'
import ModalsActions from '../../../actions/ModalsActions'

jest.mock('../../../actions/ModalsActions')
jest.mock('../../common/Select', () => ({
  Select: ({options, activeOption, onSelect}) => (
    <div data-testid="select-mock">
      <div data-testid="active-option">{activeOption?.id}</div>
      {options.map((option) => (
        <button
          key={option.id}
          data-testid={`option-${option.id}`}
          onClick={() => onSelect(option)}
        >
          {option.id}
        </button>
      ))}
    </div>
  ),
}))

const teams = [
  {
    id: 1,
    members: [
      {
        id: 10,
        user: {uid: 100, first_name: 'John', last_name: 'Doe'},
      },
      {
        id: 11,
        user: {uid: 101, first_name: 'Jane', last_name: 'Smith'},
        user_metadata: {gplus_picture: 'http://example.com/pic.png'},
      },
    ],
  },
]

const projects = [{id_team: 1, id_assignee: 100}]

afterEach(() => jest.clearAllMocks())

test('pre-selects the currently assigned member', () => {
  render(
    <BulkAssignToMember
      teams={teams}
      projects={projects}
      successCallback={jest.fn()}
    />,
  )

  expect(screen.getByTestId('active-option')).toHaveTextContent('10')
  expect(screen.getByText('Continue')).toBeEnabled()
})

test('renders every member as an option, covering both avatar branches', () => {
  render(
    <BulkAssignToMember
      teams={teams}
      projects={projects}
      successCallback={jest.fn()}
    />,
  )

  expect(screen.getByTestId('option-10')).toBeInTheDocument()
  expect(screen.getByTestId('option-11')).toBeInTheDocument()
})

test('selecting a different member and continuing calls successCallback', async () => {
  const successCallback = jest.fn()
  render(
    <BulkAssignToMember
      teams={teams}
      projects={projects}
      successCallback={successCallback}
    />,
  )

  await userEvent.click(screen.getByTestId('option-11'))
  await userEvent.click(screen.getByText('Continue'))

  expect(successCallback).toHaveBeenCalledWith({id_assignee: 11})
})

test('clicking Cancel closes the modal', async () => {
  render(
    <BulkAssignToMember
      teams={teams}
      projects={projects}
      successCallback={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByText('Cancel'))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})
