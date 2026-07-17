import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'

// `socialUrls` in OnBoardingContext.js is computed at module-evaluation time
// from `config.*`. Since setupFiles.jest.js only sets `global.config = {id_job: 2}`,
// the auth URL keys must be populated on `global.config` BEFORE SocialButtons/
// OnBoardingContext are first required, otherwise every `socialUrls.*` value is
// `undefined` and none of the guarded buttons render. `beforeAll` runs too late
// (after the module has already been evaluated once via ES import), so the
// modules under test are loaded with `require` after `global.config` is set.
global.config = {
  ...global.config,
  googleAuthURL: 'https://accounts.google.com/o/oauth2/auth',
  githubAuthUrl: 'https://github.com/login/oauth/authorize',
  microsoftAuthUrl: 'https://login.microsoftonline.com/authorize',
  linkedInAuthUrl: 'https://www.linkedin.com/oauth/authorize',
  facebookAuthUrl: 'https://www.facebook.com/dialog/oauth',
}

const SocialButtons = require('./SocialButtons').default
const {OnBoardingContext} = require('./OnBoardingContext')

const renderWithContext = (socialLogin = jest.fn()) =>
  render(
    <OnBoardingContext.Provider value={{socialLogin}}>
      <SocialButtons />
    </OnBoardingContext.Provider>,
  )

describe('SocialButtons', () => {
  test('renders a button for each configured social URL', () => {
    const {container} = renderWithContext()
    expect(container.querySelectorAll('button').length).toBe(5)
  })

  test('clicking the Google button calls socialLogin with the Google URL', () => {
    const socialLogin = jest.fn()
    renderWithContext(socialLogin)
    fireEvent.click(screen.getAllByAltText('Google login')[0])
    expect(socialLogin).toHaveBeenCalled()
  })
})
