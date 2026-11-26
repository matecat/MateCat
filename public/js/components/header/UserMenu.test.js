import {render, screen} from '@testing-library/react'
import React from 'react'

import {UserMenu} from './UserMenu'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import userMock from '../../../mocks/userMock'

test('The user menu toggle is the onboarding tour target', () => {
  render(
    <ApplicationWrapperContext.Provider
      value={{isUserLogged: true, userInfo: userMock}}
    >
      <UserMenu />
    </ApplicationWrapperContext.Provider>,
  )

  expect(screen.getByRole('button')).toHaveAttribute(
    'data-onboarding',
    'user-menu',
  )
})
