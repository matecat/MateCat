import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {BulkChangePassword} from './BulkChangePassword'
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

const jobsWithoutRevise2 = [
  {id: 1, revise_passwords: [{revision_number: 1, password: 'aaa'}]},
]

const jobsWithRevise2 = [
  {
    id: 1,
    revise_passwords: [
      {revision_number: 1, password: 'aaa'},
      {revision_number: 2, password: 'bbb'},
    ],
  },
]

afterEach(() => jest.clearAllMocks())

test('renders only Translate and Revise options when no job has a second revision', () => {
  render(
    <BulkChangePassword
      jobs={jobsWithoutRevise2}
      successCallback={jest.fn()}
    />,
  )

  expect(screen.getByTestId('option-0')).toBeInTheDocument()
  expect(screen.getByTestId('option-1')).toBeInTheDocument()
  expect(screen.queryByTestId('option-2')).not.toBeInTheDocument()
})

test('renders the Revise 2 option when a job already has a second revision', () => {
  render(
    <BulkChangePassword jobs={jobsWithRevise2} successCallback={jest.fn()} />,
  )

  expect(screen.getByTestId('option-2')).toBeInTheDocument()
  expect(screen.getByText('Revise 2')).toBeInTheDocument()
})

test('Continue is disabled until a type is selected', async () => {
  render(
    <BulkChangePassword
      jobs={jobsWithoutRevise2}
      successCallback={jest.fn()}
    />,
  )

  expect(screen.getByText('Continue')).toBeDisabled()

  await userEvent.click(screen.getByTestId('option-1'))

  expect(screen.getByText('Continue')).toBeEnabled()
})

test('selecting Translate and continuing calls successCallback with a null revision_number', async () => {
  const successCallback = jest.fn()
  render(
    <BulkChangePassword
      jobs={jobsWithoutRevise2}
      successCallback={successCallback}
    />,
  )

  await userEvent.click(screen.getByTestId('option-0'))
  await userEvent.click(screen.getByText('Continue'))

  expect(successCallback).toHaveBeenCalledWith({revision_number: null})
})

test('selecting Revise and continuing calls successCallback with revision_number 1', async () => {
  const successCallback = jest.fn()
  render(
    <BulkChangePassword
      jobs={jobsWithoutRevise2}
      successCallback={successCallback}
    />,
  )

  await userEvent.click(screen.getByTestId('option-1'))
  await userEvent.click(screen.getByText('Continue'))

  expect(successCallback).toHaveBeenCalledWith({revision_number: 1})
})

test('clicking Cancel closes the modal', async () => {
  render(
    <BulkChangePassword
      jobs={jobsWithoutRevise2}
      successCallback={jest.fn()}
    />,
  )

  await userEvent.click(screen.getByText('Cancel'))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})
