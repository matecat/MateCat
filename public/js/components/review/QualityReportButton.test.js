import {act, fireEvent, render, screen, waitFor} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import React from 'react'
import {mswServer} from '../../../mocks/mswServer'

import {QualityReportButton} from './QualityReportButton'
import CatToolStore from '../../stores/CatToolStore'
import CattoolConstants from '../../constants/CatToolConstants'
import CatToolActions from '../../actions/CatToolActions'
import CommonUtils from '../../utils/commonUtils'

jest.mock('../../actions/CatToolActions')
jest.mock('../../utils/commonUtils')

jest.mock('../common/DropdownMenu/DropdownMenu', () => {
  const actual = jest.requireActual('../common/DropdownMenu/DropdownMenu')
  return {
    ...actual,
    // Isolate QualityReportButton's own click handlers from DropdownMenu's
    // internal Radix/Popover rendering, which is covered by its own directory.
    DropdownMenu: ({toggleButtonProps, items}) => (
      <div data-testid="report-button">
        <button data-testid="toggle-button" onClick={toggleButtonProps.onClick}>
          {toggleButtonProps.children}
        </button>
        {items.map((item, index) => (
          <div key={index} onClick={item.onClick}>
            {item.label}
          </div>
        ))}
      </div>
    ),
  }
})

window.config = {
  basepath: 'http://localhost/',
  id_job: '1',
  password: '1',
}

const mockQualityReportEndpoint = (payload = {}) =>
  mswServer.use(
    http.get(config.basepath + 'api/app/jobs/1/1/quality-report', () => {
      return HttpResponse.json(payload)
    }),
  )

mockQualityReportEndpoint()

beforeEach(() => {
  jest.spyOn(window, 'open').mockImplementation(() => {})
})

afterEach(() => {
  window.open.mockRestore()
})

test('works properly', () => {
  render(<QualityReportButton />)

  expect(screen.getByTestId('report-button')).toBeVisible()
})

test('opens the feedback modal when all revisions are approved and no feedback exists yet', () => {
  mockQualityReportEndpoint()
  Object.assign(config, {isReview: true, revisionNumber: 1})
  CatToolStore.getQR = jest.fn().mockReturnValue([{feedback: null}])
  CommonUtils.getFromSessionStorage = jest.fn().mockReturnValue(false)

  render(
    <QualityReportButton
      isReview={true}
      revisionNumber={1}
      secondRevisionsCount={0}
      qualityReportHref="/quality-report"
    />,
  )

  // trigger the SET_PROGRESS listener registered by the component's useEffect
  act(() => {
    CatToolStore.emit(CattoolConstants.SET_PROGRESS, {
      raw: {approved: 10, total: 10},
    })
  })

  expect(CatToolActions.openFeedbackModal).toHaveBeenCalledWith('', 1)
})

test('does not reopen the feedback modal if the user already dismissed it this session', () => {
  mockQualityReportEndpoint()
  Object.assign(config, {isReview: true, revisionNumber: 1})
  CatToolStore.getQR = jest.fn().mockReturnValue([{feedback: null}])
  CommonUtils.getFromSessionStorage = jest.fn().mockReturnValue(true)

  render(
    <QualityReportButton
      isReview={true}
      revisionNumber={1}
      secondRevisionsCount={0}
      qualityReportHref="/quality-report"
    />,
  )
  act(() => {
    CatToolStore.emit(CattoolConstants.SET_PROGRESS, {
      raw: {approved: 10, total: 10},
    })
  })

  expect(CatToolActions.openFeedbackModal).not.toHaveBeenCalled()
})

test('invokes the toggle button and menu item click handlers of the dropdown', () => {
  mockQualityReportEndpoint()
  Object.assign(config, {isReview: true, revisionNumber: 1})
  // feedback already present so checkQualityReport does not itself trigger
  // openFeedbackModal, keeping the assertions below isolated to the click handlers
  CatToolStore.getQR = jest
    .fn()
    .mockReturnValue([{feedback: 'already reviewed'}])
  CommonUtils.getFromSessionStorage = jest.fn().mockReturnValue(true)

  render(
    <QualityReportButton
      isReview={true}
      revisionNumber={1}
      secondRevisionsCount={0}
      qualityReportHref="/quality-report"
    />,
  )
  act(() => {
    CatToolStore.emit(CattoolConstants.SET_PROGRESS, {
      raw: {approved: 10, total: 10},
    })
  })

  fireEvent.click(screen.getByTestId('toggle-button'))
  expect(window.open).toHaveBeenCalledWith('/quality-report', '_blank')

  fireEvent.click(screen.getByText('Open QR'))
  expect(window.open).toHaveBeenCalledTimes(2)

  fireEvent.click(screen.getByText('Write feedback (R1)'))
  expect(CatToolActions.openFeedbackModal).toHaveBeenCalledWith(undefined, 1)
})

test('applies review data returned by the quality report endpoint', async () => {
  Object.assign(config, {isReview: true, revisionNumber: 1})
  mockQualityReportEndpoint({
    'quality-report': {
      chunk: {
        reviews: [{revision_number: 1, is_pass: true, feedback: 'looks good'}],
      },
    },
  })

  render(
    <QualityReportButton
      isReview={true}
      revisionNumber={1}
      secondRevisionsCount={0}
      qualityReportHref="/quality-report"
    />,
  )

  await waitFor(() =>
    expect(CatToolActions.updateQualityReport).toHaveBeenCalledWith(
      expect.objectContaining({
        chunk: {
          reviews: [
            {revision_number: 1, is_pass: true, feedback: 'looks good'},
          ],
        },
      }),
    ),
  )
})
