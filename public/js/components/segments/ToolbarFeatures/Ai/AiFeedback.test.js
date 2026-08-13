import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {AiFeedback} from './AiFeedback'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CommonUtils from '../../../../utils/commonUtils'

jest.mock('../../../../actions/SegmentActions', () => ({
  aiFeedbackTab: jest.fn(),
}))

jest.mock('../../../../utils/commonUtils', () => ({
  dispatchTrackingEvents: jest.fn(),
}))

beforeAll(() => {
  global.config = {
    ...global.config,
    id_job: '42',
    isReview: false,
  }
})

afterEach(() => {
  jest.clearAllMocks()
})

const userInfo = {user: {uid: 'u1'}}

const renderComponent = (segment, props = {}) =>
  render(
    <ApplicationWrapperContext.Provider value={{userInfo}}>
      <AiFeedback sid="10" segment={segment} isIconsBundled={false} {...props} />
    </ApplicationWrapperContext.Provider>,
  )

describe('AiFeedback', () => {
  test('renders nothing when config.isReview is true', () => {
    global.config.isReview = true
    const {container} = renderComponent({status: 'DRAFT', modified: false})
    expect(container).toBeEmptyDOMElement()
    global.config.isReview = false
  })

  test('is disabled for an unmodified NEW segment', () => {
    renderComponent({status: 'NEW', modified: false})
    expect(screen.getByRole('button')).toBeDisabled()
    expect(screen.getByRole('button')).toHaveAttribute(
      'title',
      'Lara feedback - edit translation to enable',
    )
  })

  test('is disabled for an unmodified DRAFT segment', () => {
    renderComponent({status: 'DRAFT', modified: false})
    expect(screen.getByRole('button')).toBeDisabled()
  })

  test('is enabled for a modified segment and opens the feedback tab on click', () => {
    renderComponent({status: 'DRAFT', modified: true})
    const button = screen.getByRole('button')
    expect(button).not.toBeDisabled()
    expect(button).toHaveAttribute('title', 'Lara feedback')

    fireEvent.click(button)

    expect(SegmentActions.aiFeedbackTab).toHaveBeenCalledWith({sid: '10'})
    expect(CommonUtils.dispatchTrackingEvents).toHaveBeenCalledWith(
      'LaraStyle',
      expect.objectContaining({
        user: 'u1',
        jobId: '42',
        segmentId: '10',
      }),
    )
  })

  test('is enabled for a TRANSLATED status regardless of modified flag', () => {
    renderComponent({status: 'TRANSLATED', modified: false})
    expect(screen.getByRole('button')).not.toBeDisabled()
  })

  test('renders bundled label when isIconsBundled is true', () => {
    renderComponent({status: 'TRANSLATED', modified: false}, {isIconsBundled: true})
    expect(screen.getByText('Ai feedback')).toBeInTheDocument()
  })
})
