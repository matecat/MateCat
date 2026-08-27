import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {Header} from './Header'
import SegmentFilter from './segment_filter/segment_filter'
import {ApplicationWrapperContext} from '../../common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('./SubHeaderContainer', () => ({userInfo, filtersEnabled}) => (
  <div
    data-testid="sub-header-container"
    data-filters-enabled={String(!!filtersEnabled)}
    data-user={userInfo ? userInfo.email : ''}
  />
))
jest.mock('./segment_filter/segment_filter', () => ({
  enabled: jest.fn(),
}))
jest.mock('./FilesMenu', () => ({
  FilesMenu: ({projectName}) => (
    <div data-testid="files-menu">{projectName}</div>
  ),
}))
jest.mock('./MarkAsCompleteButton', () => ({
  MarkAsCompleteButton: ({featureEnabled, isReview}) => (
    <div
      data-testid="mark-as-complete"
      data-enabled={String(!!featureEnabled)}
      data-review={String(!!isReview)}
    />
  ),
}))
jest.mock('./JobMetadata', () => ({metadata}) => (
  <div data-testid="job-metadata" data-has-metadata={String(!!metadata)} />
))
jest.mock('../../review/QualityReportButton', () => ({
  QualityReportButton: (props) => (
    <div data-testid="quality-report-button" data-props={JSON.stringify(props)} />
  ),
}))
jest.mock('./DownloadMenu', () => ({
  DownloadMenu: ({password, jid, isGDriveProject}) => (
    <div
      data-testid="download-menu"
      data-password={password}
      data-jid={jid}
      data-gdrive={String(!!isGDriveProject)}
    />
  ),
}))
jest.mock('./SegmetsQAButton', () => ({
  SegmentsQAButton: () => <div data-testid="segments-qa-button" />,
}))
jest.mock('./SearchButton', () => ({
  SearchButton: () => <div data-testid="search-button" />,
}))
jest.mock('./CommentsButton', () => ({
  CommentsButton: () => <div data-testid="comments-button" />,
}))
jest.mock('./SegmentsFilterButton', () => ({
  SegmentsFilterButton: () => <div data-testid="segments-filter-button" />,
}))
jest.mock('../ActionMenu', () => ({
  ActionMenu: (props) => (
    <div data-testid="action-menu" data-props={JSON.stringify(props)} />
  ),
}))
jest.mock('../UserMenu', () => ({
  UserMenu: () => <div data-testid="user-menu" />,
}))

const props = {
  jid: 'job-1',
  pid: 'project-1',
  password: 'pw',
  reviewPassword: 'rpw',
  projectName: 'My project',
  source_code: 'en-US',
  target_code: 'it-IT',
  revisionNumber: 1,
  projectCompletionEnabled: true,
  isReview: false,
  secondRevisionsCount: 0,
  qualityReportHref: '/qr',
  allowLinkToAnalysis: true,
  analysisEnabled: true,
  isGDriveProject: false,
  showReviseLink: false,
  openTmPanel: jest.fn(),
  jobMetadata: {project: {}},
}

const renderHeader = (overrideProps = {}, contextValue = {}) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{
        isUserLogged: true,
        userInfo: {email: 'translator@matecat.com'},
        ...contextValue,
      }}
    >
      <Header {...props} {...overrideProps} />
    </ApplicationWrapperContext.Provider>,
  )

beforeEach(() => {
  global.config = {}
  jest.clearAllMocks()
  SegmentFilter.enabled.mockReturnValue(true)
})

test('renders the cattool header content and wires props to its children when logged in', () => {
  renderHeader()

  expect(screen.getByText('My project')).toBeInTheDocument()
  expect(screen.getByTestId('mark-as-complete')).toHaveAttribute(
    'data-enabled',
    'true',
  )
  expect(screen.getByTestId('job-metadata')).toHaveAttribute(
    'data-has-metadata',
    'true',
  )
  expect(screen.getByTestId('download-menu')).toHaveAttribute(
    'data-jid',
    'job-1',
  )
  expect(screen.getByTestId('search-button')).toBeInTheDocument()
  expect(screen.getByTestId('segments-filter-button')).toBeInTheDocument()
  expect(screen.getByTestId('comments-button')).toBeInTheDocument()
  expect(screen.getByTestId('segments-qa-button')).toBeInTheDocument()
  expect(screen.getByTestId('quality-report-button')).toBeInTheDocument()
  expect(screen.getByTestId('action-menu')).toBeInTheDocument()
  expect(screen.getByTestId('user-menu')).toBeInTheDocument()

  const subHeader = screen.getByTestId('sub-header-container')
  expect(subHeader).toHaveAttribute('data-filters-enabled', 'true')
  expect(subHeader).toHaveAttribute('data-user', 'translator@matecat.com')
})

test('hides the cattool-only content but keeps the user menu and subheader when not logged in', () => {
  renderHeader({}, {isUserLogged: false})

  expect(screen.queryByTestId('files-menu')).toBeNull()
  expect(screen.queryByTestId('mark-as-complete')).toBeNull()
  expect(screen.queryByTestId('action-menu')).toBeNull()
  expect(screen.getByTestId('user-menu')).toBeInTheDocument()
  expect(screen.getByTestId('sub-header-container')).toBeInTheDocument()
})

test('shows the revision mark only when a revision is active', () => {
  const {rerender} = renderHeader({revisionNumber: 2})
  expect(screen.getByText('R2')).toBeInTheDocument()

  rerender(
    <ApplicationWrapperContext.Provider
      value={{isUserLogged: true, userInfo: {}}}
    >
      <Header {...props} revisionNumber={0} />
    </ApplicationWrapperContext.Provider>,
  )
  expect(screen.queryByText(/^R\d/)).toBeNull()
})

test('does not render the mark-as-complete button when the feature is disabled', () => {
  renderHeader({projectCompletionEnabled: false})
  expect(screen.queryByTestId('mark-as-complete')).toBeNull()
})

test('calls openTmPanel when the settings icon is clicked', () => {
  renderHeader()
  const settingsButton = screen.getAllByRole('button').slice(-1)[0]
  fireEvent.click(settingsButton)
  expect(props.openTmPanel).toHaveBeenCalledTimes(1)
})

test('reflects the segment filter availability in the subheader props', () => {
  SegmentFilter.enabled.mockReturnValue(false)
  renderHeader()
  expect(screen.getByTestId('sub-header-container')).toHaveAttribute(
    'data-filters-enabled',
    'false',
  )
})
