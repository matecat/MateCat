import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import {fromJS} from 'immutable'

import MembersFilter from './MembersFilter'
import ManageConstants from '../../../constants/ManageConstants'

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

window.ResizeObserver = ResizeObserver

const members = [
  {
    user: {uid: 1, first_name: 'Pierluigi', last_name: 'Di Cianni'},
    projects: 5,
  },
  {
    user: {uid: 2, first_name: 'Federico', last_name: 'Ricciuti'},
    user_metadata: {gplus_picture: 'https://example.com/pic.png'},
    projects: 0,
  },
]

const selectedTeam = fromJS({members})

const openPopover = () => userEvent.click(screen.getByRole('button'))

test('Shows "All Members" by default', () => {
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={ManageConstants.ALL_MEMBERS_FILTER}
      setCurrentUser={jest.fn()}
    />,
  )

  expect(screen.getByRole('button')).toHaveTextContent('All Members')
})

test('Shows "Not assigned" when that filter is selected', () => {
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={ManageConstants.NOT_ASSIGNED_FILTER}
      setCurrentUser={jest.fn()}
    />,
  )

  expect(screen.getByRole('button')).toHaveTextContent('Not assigned')
})

test('Shows the selected member full name with initials when there is no picture', () => {
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={members[0]}
      setCurrentUser={jest.fn()}
    />,
  )

  expect(screen.getByRole('button')).toHaveTextContent('Pierluigi Di Cianni')
  expect(screen.getByText('PD')).toBeInTheDocument()
})

test('Shows the selected member profile picture when available', () => {
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={members[1]}
      setCurrentUser={jest.fn()}
    />,
  )

  expect(document.querySelector('img.ui-user-dropdown-image')).toHaveAttribute(
    'src',
    'https://example.com/pic.png',
  )
})

test('Lists every member with their project count', async () => {
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={ManageConstants.ALL_MEMBERS_FILTER}
      setCurrentUser={jest.fn()}
    />,
  )

  await openPopover()

  expect(screen.getByText('Pierluigi Di Cianni')).toBeInTheDocument()
  expect(screen.getByText('Federico Ricciuti')).toBeInTheDocument()
  expect(screen.getByText('5')).toBeInTheDocument()
})

test('Selecting "Not assigned" updates the current user', async () => {
  const setCurrentUser = jest.fn()
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={ManageConstants.ALL_MEMBERS_FILTER}
      setCurrentUser={setCurrentUser}
    />,
  )

  await openPopover()
  await userEvent.click(screen.getByText('Not assigned'))

  expect(setCurrentUser).toHaveBeenCalledWith(
    ManageConstants.NOT_ASSIGNED_FILTER,
  )
})

test('Selecting "All Members" updates the current user', async () => {
  const setCurrentUser = jest.fn()
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={ManageConstants.NOT_ASSIGNED_FILTER}
      setCurrentUser={setCurrentUser}
    />,
  )

  await openPopover()
  await userEvent.click(screen.getByText('All Members'))

  expect(setCurrentUser).toHaveBeenCalledWith(
    ManageConstants.ALL_MEMBERS_FILTER,
  )
})

test('Selecting a member updates the current user', async () => {
  const setCurrentUser = jest.fn()
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={ManageConstants.ALL_MEMBERS_FILTER}
      setCurrentUser={setCurrentUser}
    />,
  )

  await openPopover()
  await userEvent.click(screen.getByText('Federico Ricciuti'))

  expect(setCurrentUser).toHaveBeenCalledWith(members[1])
})

test('Filters the member list by search term', async () => {
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={ManageConstants.ALL_MEMBERS_FILTER}
      setCurrentUser={jest.fn()}
    />,
  )

  await openPopover()
  await userEvent.type(
    screen.getByPlaceholderText('Search by name'),
    'federico',
  )

  expect(screen.getByText('Federico Ricciuti')).toBeInTheDocument()
  expect(screen.queryByText('Pierluigi Di Cianni')).not.toBeInTheDocument()
})

test('Shows "No results found" when the search matches nothing', async () => {
  render(
    <MembersFilter
      selectedTeam={selectedTeam}
      currentUser={ManageConstants.ALL_MEMBERS_FILTER}
      setCurrentUser={jest.fn()}
    />,
  )

  await openPopover()
  await userEvent.type(screen.getByPlaceholderText('Search by name'), 'nobody')

  expect(screen.getByText('No results found.')).toBeInTheDocument()
})
