import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import '@testing-library/jest-dom'

import SegmentsCommentsIcon from './SegmentsCommentsIcon'
import {SegmentContext} from './SegmentContext'
import CommentsStore from '../../stores/CommentsStore'
import SegmentActions from '../../actions/SegmentActions'
import SegmentUtils from '../../utils/segmentUtils'

jest.mock('../../stores/CommentsStore', () => ({
  getCommentsCountBySegment: jest.fn(),
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

jest.mock('../../actions/SegmentActions', () => ({
  openSegmentComment: jest.fn(),
  openSegment: jest.fn(),
}))

jest.mock('../../constants/CommentsConstants', () => ({
  ADD_COMMENT: 'ADD_COMMENT',
  STORE_COMMENTS: 'STORE_COMMENTS',
}))

jest.mock('../../utils/segmentUtils', () => ({
  isReadonlySegment: jest.fn(),
}))

const renderIcon = (segment, comments) => {
  CommentsStore.getCommentsCountBySegment.mockReturnValue(comments)
  return render(
    <SegmentContext.Provider value={{segment}}>
      <SegmentsCommentsIcon />
    </SegmentContext.Provider>,
  )
}

describe('SegmentsCommentsIcon', () => {
  const baseSegment = {sid: '10-1', original_sid: 10, splitted: false}

  test('renders nothing when there are no comments', () => {
    const {container} = renderIcon(baseSegment, null)
    expect(container).toBeEmptyDOMElement()
  })

  test('renders nothing when segment is splitted and not the first of the group', () => {
    const segment = {sid: '10-2', original_sid: 10, splitted: true}
    const {container} = renderIcon(segment, {total: 1, active: 1})
    expect(container).toBeEmptyDOMElement()
  })

  test('renders when segment is splitted and is the first of the group', () => {
    const segment = {sid: '10-1', original_sid: 10, splitted: true}
    renderIcon(segment, {total: 1, active: 1})
    expect(document.querySelector('.comment-icon-btn')).toBeInTheDocument()
  })

  test('renders "+" badge when total comments is zero', () => {
    renderIcon(baseSegment, {total: 0, active: 0})
    expect(screen.getByText('+')).toBeInTheDocument()
  })

  test('renders "+" badge when there are comments but none active', () => {
    renderIcon(baseSegment, {total: 3, active: 0})
    expect(screen.getByText('+')).toBeInTheDocument()
  })

  test('renders active count badge and has-object class when there are active comments', () => {
    renderIcon(baseSegment, {total: 3, active: 2})
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(document.querySelector('.comment-icon-btn')).toHaveClass(
      'has-object',
    )
  })

  test('sets title using shortcut keystrokes', () => {
    renderIcon(baseSegment, {total: 1, active: 1})
    const btn = document.querySelector('.comment-icon-btn')
    expect(btn.getAttribute('title')).toMatch(/^Add comment \(.+\)$/)
  })

  test('clicking opens segment comment and opens segment when not readonly', () => {
    SegmentUtils.isReadonlySegment.mockReturnValue(false)
    renderIcon(baseSegment, {total: 1, active: 1})
    fireEvent.click(document.querySelector('.comment-icon-btn'))
    expect(SegmentActions.openSegmentComment).toHaveBeenCalledWith(
      baseSegment.sid,
    )
    expect(SegmentActions.openSegment).toHaveBeenCalledWith(baseSegment.sid)
  })

  test('clicking does not open segment when readonly', () => {
    SegmentUtils.isReadonlySegment.mockReturnValue(true)
    renderIcon(baseSegment, {total: 1, active: 1})
    fireEvent.click(document.querySelector('.comment-icon-btn'))
    expect(SegmentActions.openSegmentComment).toHaveBeenCalledWith(
      baseSegment.sid,
    )
    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
  })

  test('registers and unregisters store listeners on mount/unmount', () => {
    const {unmount} = renderIcon(baseSegment, {total: 1, active: 1})
    expect(CommentsStore.addListener).toHaveBeenCalledWith(
      'ADD_COMMENT',
      expect.any(Function),
    )
    expect(CommentsStore.addListener).toHaveBeenCalledWith(
      'STORE_COMMENTS',
      expect.any(Function),
    )
    unmount()
    expect(CommentsStore.removeListener).toHaveBeenCalledWith(
      'ADD_COMMENT',
      expect.any(Function),
    )
    expect(CommentsStore.removeListener).toHaveBeenCalledWith(
      'STORE_COMMENTS',
      expect.any(Function),
    )
  })

  test('updateComments ignores updates for a different segment when sid is provided', () => {
    renderIcon(baseSegment, {total: 1, active: 1})
    const updateComments = CommentsStore.addListener.mock.calls[0][1]
    CommentsStore.getCommentsCountBySegment.mockReturnValue({
      total: 5,
      active: 5,
    })
    updateComments('other-sid')
    // active count should still reflect original render (5 not shown since ignored)
    expect(screen.queryByText('5')).not.toBeInTheDocument()
  })

  test('updateComments updates when sid matches current segment', () => {
    renderIcon(baseSegment, {total: 1, active: 1})
    const updateComments = CommentsStore.addListener.mock.calls[0][1]
    CommentsStore.getCommentsCountBySegment.mockReturnValue({
      total: 5,
      active: 5,
    })
    act(() => {
      updateComments(baseSegment.sid)
    })
    expect(screen.getByText('5')).toBeInTheDocument()
  })
})
