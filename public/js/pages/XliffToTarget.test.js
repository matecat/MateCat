import React from 'react'
import {render, screen} from '@testing-library/react'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('./mountPage', () => ({mountPage: jest.fn()}))
jest.mock('../components/header/Header', () => (props) => (
  <div data-testid="header" data-logged={String(!!props.loggedUser)} />
))
jest.mock('../components/footer/Footer', () => () => (
  <div data-testid="footer" />
))
jest.mock('../sse/SocketListener', () => (props) => (
  <div data-testid="socket-listener" data-userid={String(props.userId)} />
))
jest.mock('../components/xliffToTarget/UploadXliff', () => ({
  UploadXliff: () => <div data-testid="upload-xliff" />,
}))

// XliffToTarget.js reads `document.querySelector('header.upload-page-header')`
// at module-evaluation time (top of the file, outside the component), so that
// element must already exist in the DOM the first time the module is
// evaluated. A static top-level `import` is hoisted above any other
// statement by Babel's CommonJS transform (so creating the element above an
// `import` wouldn't help), so instead the element is created in `beforeAll`
// and the module is `require`'d lazily there.
let XliffToTarget

beforeAll(() => {
  const header = document.createElement('header')
  header.className = 'upload-page-header'
  document.body.appendChild(header)
  ;({XliffToTarget} = require('./XliffToTarget'))
})

const renderPage = (contextOverrides = {}) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{
        isUserLogged: true,
        userInfo: {user: {uid: 1}},
        ...contextOverrides,
      }}
    >
      <XliffToTarget />
    </ApplicationWrapperContext.Provider>,
  )

describe('XliffToTarget', () => {
  test('renders Header, Footer and SocketListener', () => {
    renderPage()
    expect(screen.getByTestId('header')).toBeInTheDocument()
    expect(screen.getByTestId('footer')).toBeInTheDocument()
    expect(screen.getByTestId('socket-listener')).toBeInTheDocument()
  })

  test('renders UploadXliff when isUserLogged is defined', () => {
    renderPage({isUserLogged: true})
    expect(screen.getByTestId('upload-xliff')).toBeInTheDocument()
  })

  test('renders UploadXliff when isUserLogged is false', () => {
    renderPage({isUserLogged: false})
    expect(screen.getByTestId('upload-xliff')).toBeInTheDocument()
  })

  test('does not render UploadXliff when isUserLogged is undefined', () => {
    renderPage({isUserLogged: undefined, userInfo: undefined})
    expect(screen.queryByTestId('upload-xliff')).not.toBeInTheDocument()
  })

  test('passes userId to SocketListener when logged in', () => {
    renderPage({isUserLogged: true, userInfo: {user: {uid: 42}}})
    expect(screen.getByTestId('socket-listener')).toHaveAttribute(
      'data-userid',
      '42',
    )
  })
})
