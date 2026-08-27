import React from 'react'
import {render, screen} from '@testing-library/react'
import {UserProjectDropdown} from './UserProjectDropdown'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import userMock from '../../../mocks/userMock'

const renderWithContext = (project) =>
  render(
    <ApplicationWrapperContext.Provider value={{userInfo: userMock}}>
      <UserProjectDropdown
        users={[]}
        project={project}
        openAddMember={jest.fn()}
        changeUser={jest.fn()}
        idAssignee={undefined}
      />
    </ApplicationWrapperContext.Provider>,
  )

describe('UserProjectDropdown', () => {
  test('does not throw when the project team is not in the user teams list', () => {
    expect(() => renderWithContext({id_team: 999999999})).not.toThrow()
  })

  test('does not disable assignment when the project team is not in the user teams list', () => {
    renderWithContext({id_team: 999999999})

    expect(screen.getByTestId('project-teams')).not.toBeDisabled()
  })

  test('disables assignment when the project belongs to the personal team', () => {
    const personalTeam = userMock.teams.find(({type}) => type === 'personal')
    renderWithContext({id_team: personalTeam.id})

    expect(screen.getByTestId('project-teams')).toBeDisabled()
  })
})
