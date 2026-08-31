import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {LaraStyles} from './LaraStyles'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CommonUtils from '../../../../utils/commonUtils'
import CatToolStore from '../../../../stores/CatToolStore'

jest.mock('../../../../actions/SegmentActions', () => ({
  laraStylesTab: jest.fn(),
}))

jest.mock('../../../../utils/commonUtils', () => ({
  dispatchTrackingEvents: jest.fn(),
}))

jest.mock('../../../../stores/CatToolStore', () => ({
  getJobMetadata: jest.fn(() => ({
    project: {mt_extra: {lara_style: 'faithful'}},
  })),
}))

jest.mock(
  '../../../settingsPanel/Contents/MachineTranslationTab/LaraOptions/LaraOptions',
  () => ({
    LARA_STYLES_OPTIONS: [
      {id: 'faithful', name: 'Faithful'},
      {id: 'fluent', name: 'Fluent'},
    ],
  }),
)

beforeAll(() => {
  global.config = {
    ...global.config,
    id_job: '42',
    isReview: false,
  }
})

afterEach(() => {
  jest.clearAllMocks()
  CatToolStore.getJobMetadata.mockReturnValue({
    project: {mt_extra: {lara_style: 'faithful'}},
  })
})

const userInfo = {user: {uid: 'u1'}}

const renderComponent = (segment, props = {}) =>
  render(
    <ApplicationWrapperContext.Provider value={{userInfo}}>
      <LaraStyles sid="10" segment={segment} isIconsBundled={false} {...props} />
    </ApplicationWrapperContext.Provider>,
  )

describe('LaraStyles', () => {
  test('renders nothing when config.isReview is true', () => {
    global.config.isReview = true
    const {container} = renderComponent({
      status: 'DRAFT',
      contributions: {},
    })
    expect(container).toBeEmptyDOMElement()
    global.config.isReview = false
  })

  test('is disabled when segment has no contributions', () => {
    renderComponent({status: 'DRAFT', contributions: undefined})
    expect(screen.getByRole('button')).toBeDisabled()
    expect(screen.getByRole('button')).toHaveAttribute(
      'title',
      'Lara styles - Available for unconfirmed segments only',
    )
  })

  test('is disabled when segment status is not NEW or DRAFT', () => {
    renderComponent({status: 'TRANSLATED', contributions: {}})
    expect(screen.getByRole('button')).toBeDisabled()
  })

  test('is enabled for a DRAFT segment with contributions and opens the styles tab on click', () => {
    renderComponent({status: 'DRAFT', contributions: {}})
    const button = screen.getByRole('button')
    expect(button).not.toBeDisabled()
    expect(button).toHaveAttribute(
      'title',
      'Lara styles - Click to see translations in different styles',
    )

    fireEvent.click(button)

    expect(SegmentActions.laraStylesTab).toHaveBeenCalledWith({
      sid: '10',
      styles: [
        {id: 'faithful', name: 'Faithful', isDefault: true},
        {id: 'fluent', name: 'Fluent'},
      ],
    })
    expect(CommonUtils.dispatchTrackingEvents).toHaveBeenCalledWith(
      'LaraStyle',
      expect.objectContaining({
        user: 'u1',
        jobId: '42',
        segmentId: '10',
        style: 'Fluent',
      }),
    )
  })

  test('is enabled for a NEW segment with contributions', () => {
    renderComponent({status: 'NEW', contributions: {}})
    expect(screen.getByRole('button')).not.toBeDisabled()
  })

  test('renders bundled label when isIconsBundled is true', () => {
    renderComponent({status: 'DRAFT', contributions: {}}, {isIconsBundled: true})
    expect(screen.getByText('Lara styles')).toBeInTheDocument()
  })
})
