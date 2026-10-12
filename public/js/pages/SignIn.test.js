import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('./mountPage', () => ({mountPage: jest.fn()}))
jest.mock('../components/header/Header', () => (props) => (
  <div data-testid="header" data-showlinks={String(!!props.showLinks)} />
))
jest.mock('../components/onBoarding/OnBoarding', () => () => (
  <div data-testid="onboarding" />
))

import SignIn from './SignIn'

const renderPage = (contextOverrides = {}) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{isUserLogged: false, ...contextOverrides}}
    >
      <SignIn />
    </ApplicationWrapperContext.Provider>,
  )

describe('SignIn', () => {
  test('renders Header and OnBoarding', () => {
    renderPage()
    expect(screen.getByTestId('header')).toBeInTheDocument()
    expect(screen.getByTestId('onboarding')).toBeInTheDocument()
  })

  test('does not redirect when user is not logged in', async () => {
    // See the redirect test below for why we assert via the jsdomError
    // console.error instead of reading `window.location.href` directly.
    window.history.pushState(null, '', '/signin')
    const consoleError = jest
      .spyOn(console, 'error')
      .mockImplementation(() => {})

    renderPage({isUserLogged: false})

    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(consoleError).not.toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'not implemented',
        message: expect.stringContaining('navigation'),
      }),
    )

    consoleError.mockRestore()
  })

  test('redirects to origin when user is logged in', async () => {
    // `window.location.href` is a non-configurable ("Unforgeable") own
    // property in this jsdom version, so it cannot be replaced with
    // `Object.defineProperty` or intercepted with `jest.spyOn`. jsdom's real
    // navigation is a documented no-op that reports itself (asynchronously)
    // via a "not implemented: navigation" jsdomError, which the environment's
    // virtual console forwards to `console.error`. We assert on that error as
    // evidence that the redirect effect really assigned `window.location.href`.
    // The test page must start on a path other than "/" (the jsdom test
    // default), otherwise reassigning to `window.location.origin` resolves to
    // the exact same URL and jsdom treats it as a no-op instead of a navigation.
    window.history.pushState(null, '', '/signin')
    const consoleError = jest
      .spyOn(console, 'error')
      .mockImplementation(() => {})

    renderPage({isUserLogged: true})

    await waitFor(() =>
      expect(consoleError).toHaveBeenCalledWith(
        expect.objectContaining({
          type: 'not implemented',
          message: expect.stringContaining('navigation'),
        }),
      ),
    )

    consoleError.mockRestore()
  })
})
