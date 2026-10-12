import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {UploadFile} from './UploadFile'
import {CreateProjectContext} from './CreateProjectContext'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import ModalsActions from '../../actions/ModalsActions'
import {initFileUpload} from '../../api/initFileUpload'
import {clearNotCompletedUploads} from '../../api/clearNotCompletedUploads'

jest.mock('../../actions/ModalsActions', () => ({
  openLoginModal: jest.fn(),
}))
jest.mock('../../api/initFileUpload', () => ({
  initFileUpload: jest.fn(),
}))
jest.mock('../../api/clearNotCompletedUploads', () => ({
  clearNotCompletedUploads: jest.fn(),
}))
jest.mock('./UploadFileLocal', () => () => <div data-testid="upload-file-local" />)
jest.mock('./UploadGdrive', () => ({
  UploadGdrive: () => <div data-testid="upload-gdrive" />,
}))

const defaultCreateProjectValue = {
  openGDrive: false,
  currentProjectTemplate: {id: 1},
}

const renderWithContext = (
  createProjectOverrides = {},
  appOverrides = {isUserLogged: true},
) =>
  render(
    <ApplicationWrapperContext.Provider value={appOverrides}>
      <CreateProjectContext.Provider
        value={{...defaultCreateProjectValue, ...createProjectOverrides}}
      >
        <UploadFile />
      </CreateProjectContext.Provider>
    </ApplicationWrapperContext.Provider>,
  )

describe('UploadFile', () => {
  beforeEach(() => jest.clearAllMocks())

  test('calls initFileUpload on mount', () => {
    renderWithContext()
    expect(initFileUpload).toHaveBeenCalled()
  })

  test('shows local upload and GDrive components when logged in with a template', () => {
    renderWithContext()
    expect(screen.getByTestId('upload-file-local')).toBeInTheDocument()
    expect(screen.getByTestId('upload-gdrive')).toBeInTheDocument()
  })

  test('hides local upload when openGDrive is true, but keeps GDrive mounted', () => {
    renderWithContext({openGDrive: true})
    expect(screen.queryByTestId('upload-file-local')).not.toBeInTheDocument()
    expect(screen.getByTestId('upload-gdrive')).toBeInTheDocument()
  })

  test('shows the sign-in prompt when the user is not logged in', () => {
    renderWithContext({}, {isUserLogged: false})
    expect(screen.getByText('Sign in')).toBeInTheDocument()
    expect(screen.getByText('Start translating now!')).toBeInTheDocument()
  })

  test('clicking "Sign in" opens the login modal', () => {
    renderWithContext({}, {isUserLogged: false})
    fireEvent.click(screen.getByText('Sign in'))
    expect(ModalsActions.openLoginModal).toHaveBeenCalled()
  })

  test('shows a waiting placeholder while isUserLogged is undefined', () => {
    const {container} = renderWithContext({}, {isUserLogged: undefined})
    expect(container.querySelector('.upload-waiting-logged')).toBeInTheDocument()
  })

  test('calls clearNotCompletedUploads on beforeunload and cleans up the listener on unmount', () => {
    const {unmount} = renderWithContext()
    window.dispatchEvent(new Event('beforeunload'))
    expect(clearNotCompletedUploads).toHaveBeenCalled()
    unmount()
    jest.clearAllMocks()
    window.dispatchEvent(new Event('beforeunload'))
    expect(clearNotCompletedUploads).not.toHaveBeenCalled()
  })
})
