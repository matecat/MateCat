import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {AiAlternatives} from './AiAlternatives'
import SegmentActions from '../../../../actions/SegmentActions'
import {ApplicationWrapperContext} from '../../../common/ApplicationWrapper/ApplicationWrapperContext'
import CommonUtils from '../../../../utils/commonUtils'
import {getSelectedTextWithTags} from '../../utils/DraftMatecatUtils/getSelectedTextWithTags'

jest.mock('../../../../actions/SegmentActions', () => ({
  aiAlternativeTab: jest.fn(),
}))

jest.mock('../../../../utils/commonUtils', () => ({
  dispatchTrackingEvents: jest.fn(),
}))

jest.mock('../../utils/DraftMatecatUtils/getSelectedTextWithTags', () => ({
  getSelectedTextWithTags: jest.fn(),
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

const renderComponent = (props = {}) =>
  render(
    <ApplicationWrapperContext.Provider value={{userInfo}}>
      <AiAlternatives
        sid="10"
        editArea={undefined}
        isIconsBundled={false}
        {...props}
      />
    </ApplicationWrapperContext.Provider>,
  )

describe('AiAlternatives', () => {
  test('renders nothing when config.isReview is true', () => {
    global.config.isReview = true
    const {container} = renderComponent()
    expect(container).toBeEmptyDOMElement()
    global.config.isReview = false
  })

  test('is disabled with no selected text and no editArea', () => {
    renderComponent()
    expect(screen.getByRole('button')).toBeDisabled()
    expect(screen.getByRole('button')).toHaveAttribute(
      'title',
      'Alternative translations by Lara - Highlight parts of the target text to enable',
    )
  })

  test('renders enabled with selected text and focused editArea, then opens the tab', () => {
    getSelectedTextWithTags.mockReturnValue([
      {value: 'Hello'},
      {value: ' world'},
    ])
    const editAreaRef = document.createElement('div')
    document.body.appendChild(editAreaRef)
    editAreaRef.focus = () => {}
    Object.defineProperty(document, 'activeElement', {
      value: editAreaRef,
      configurable: true,
    })

    const editArea = {
      state: {editorState: {}},
      editAreaRef: {contains: () => true},
    }

    renderComponent({editArea})

    const button = screen.getByRole('button')
    expect(button).not.toBeDisabled()
    expect(button).toHaveAttribute(
      'title',
      'Alternative translations by Lara',
    )

    fireEvent.click(button)

    expect(SegmentActions.aiAlternativeTab).toHaveBeenCalledWith({
      sid: '10',
      text: 'Hello world',
    })
    expect(CommonUtils.dispatchTrackingEvents).toHaveBeenCalledWith(
      'LaraStyle',
      expect.objectContaining({
        user: 'u1',
        jobId: '42',
        segmentId: '10',
        selectedText: 'Hello world',
      }),
    )

    document.body.removeChild(editAreaRef)
  })

  test('renders bundled label and classnames when isIconsBundled is true', () => {
    renderComponent({isIconsBundled: true})
    expect(screen.getByText('Ai alternatives')).toBeInTheDocument()
  })

  test('is disabled when selected text exists but editArea is not focused', () => {
    getSelectedTextWithTags.mockReturnValue([{value: 'Hello'}])
    const editArea = {
      state: {editorState: {}},
      editAreaRef: {contains: () => false},
    }
    renderComponent({editArea})
    expect(screen.getByRole('button')).toBeDisabled()
  })
})
