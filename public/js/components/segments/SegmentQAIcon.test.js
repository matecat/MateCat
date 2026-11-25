import React from 'react'
import {render, fireEvent, act} from '@testing-library/react'
import '@testing-library/jest-dom'

import {SegmentQAIcon} from './SegmentQAIcon'
import SegmentStore from '../../stores/SegmentStore'
import SegmentActions from '../../actions/SegmentActions'

jest.mock('../../stores/SegmentStore', () => ({
  hasGlobalErrors: jest.fn(),
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../constants/SegmentConstants', () => ({
  UPDATE_GLOBAL_WARNINGS: 'UPDATE_GLOBAL_WARNINGS',
}))

jest.mock('../../actions/SegmentActions', () => ({
  openSegment: jest.fn(),
}))

describe('SegmentQAIcon', () => {
  beforeEach(() => {
    window.config = {isReview: false}
  })

  test('renders nothing when there are no global errors', () => {
    SegmentStore.hasGlobalErrors.mockReturnValue(false)
    const {container} = render(<SegmentQAIcon sid="10-1" />)
    expect(container.querySelector('.icon-warning-sign')).not.toBeInTheDocument()
  })

  test('renders warning icon when there are global errors', () => {
    SegmentStore.hasGlobalErrors.mockReturnValue(true)
    const {container} = render(<SegmentQAIcon sid="10-1" />)
    expect(container.querySelector('.icon-warning-sign')).toBeInTheDocument()
    expect(container.querySelector('.icon-warning-sign')).not.toHaveClass(
      'review',
    )
  })

  test('adds review class when config.isReview is true', () => {
    window.config = {isReview: true}
    SegmentStore.hasGlobalErrors.mockReturnValue(true)
    const {container} = render(<SegmentQAIcon sid="10-1" />)
    expect(container.querySelector('.icon-warning-sign')).toHaveClass(
      'review',
    )
  })

  test('clicking the icon opens the segment', () => {
    SegmentStore.hasGlobalErrors.mockReturnValue(true)
    const {container} = render(<SegmentQAIcon sid="10-1" />)
    fireEvent.click(container.querySelector('.icon-warning-sign'))
    expect(SegmentActions.openSegment).toHaveBeenCalledWith('10-1')
  })

  test('does not compute global errors when sid is not provided', () => {
    SegmentStore.hasGlobalErrors.mockReturnValue(true)
    render(<SegmentQAIcon />)
    expect(SegmentStore.hasGlobalErrors).not.toHaveBeenCalled()
  })

  test('registers and unregisters store listener on mount/unmount', () => {
    SegmentStore.hasGlobalErrors.mockReturnValue(false)
    const {unmount} = render(<SegmentQAIcon sid="10-1" />)
    expect(SegmentStore.addListener).toHaveBeenCalledWith(
      'UPDATE_GLOBAL_WARNINGS',
      expect.any(Function),
    )
    unmount()
    expect(SegmentStore.removeListener).toHaveBeenCalledWith(
      'UPDATE_GLOBAL_WARNINGS',
      expect.any(Function),
    )
  })

  test('listener callback updates warning state when triggered', () => {
    SegmentStore.hasGlobalErrors.mockReturnValue(false)
    const {container} = render(<SegmentQAIcon sid="10-1" />)
    expect(container.querySelector('.icon-warning-sign')).not.toBeInTheDocument()

    SegmentStore.hasGlobalErrors.mockReturnValue(true)
    const listenerCallback = SegmentStore.addListener.mock.calls[0][1]
    act(() => {
      listenerCallback()
    })

    expect(container.querySelector('.icon-warning-sign')).toBeInTheDocument()
  })
})
