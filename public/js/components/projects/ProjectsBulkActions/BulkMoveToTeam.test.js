import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import BulkMoveToTeam from './BulkMoveToTeam'
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
          {option.name}
        </button>
      ))}
    </div>
  ),
}))

const teams = [
  {id: 1, name: 'Personal'},
  {id: 2, name: 'Marketing'},
]

afterEach(() => jest.clearAllMocks())

test('pre-selects the team when all projects share the same team', () => {
  const projects = [{id_team: 1}, {id_team: 1}]

  render(
    <BulkMoveToTeam
      teams={teams}
      projects={projects}
      successCallback={jest.fn()}
    />,
  )

  expect(screen.getByTestId('active-option')).toHaveTextContent('1')
  expect(screen.getByText('Continue')).toBeEnabled()
})

test('does not pre-select a team when projects belong to different teams', () => {
  const projects = [{id_team: 1}, {id_team: 2}]

  render(
    <BulkMoveToTeam
      teams={teams}
      projects={projects}
      successCallback={jest.fn()}
    />,
  )

  expect(screen.getByTestId('active-option')).toBeEmptyDOMElement()
  expect(screen.getByText('Continue')).toBeDisabled()
})

test('selecting a team and continuing calls successCallback with the new team id', async () => {
  const projects = [{id_team: 1}, {id_team: 2}]
  const successCallback = jest.fn()

  render(
    <BulkMoveToTeam
      teams={teams}
      projects={projects}
      successCallback={successCallback}
    />,
  )

  await userEvent.click(screen.getByTestId('option-2'))
  await userEvent.click(screen.getByText('Continue'))

  expect(successCallback).toHaveBeenCalledWith({id_team: 2})
})

test('clicking Cancel closes the modal', async () => {
  const projects = [{id_team: 1}, {id_team: 1}]

  render(
    <BulkMoveToTeam
      teams={teams}
      projects={projects}
      successCallback={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByText('Cancel'))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})
