import React from 'react'
import {act, render, screen} from '@testing-library/react'
import {fromJS} from 'immutable'

import AnalyzeHeader from './AnalyzeHeader'
import {ANALYSIS_STATUS} from '../../constants/Constants'

const project = fromJS({id: 1, name: 'Test project'})

const buildData = (totalEquivalent) =>
  fromJS({
    status: ANALYSIS_STATUS.DONE,
    segments_analyzed: 10,
    total_segments: 10,
    in_queue_before: 0,
    total_raw: 100,
    total_equivalent: totalEquivalent,
  })

describe('AnalyzeHeader saving percentage flash', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  test('does not flash on initial render', () => {
    render(<AnalyzeHeader data={buildData(50)} project={project} />)

    expect(screen.getByTestId('word-count-box')).not.toHaveClass(
      'updated-count',
    )
  })

  test('flashes and then clears when the saving percentage changes', () => {
    const {rerender} = render(
      <AnalyzeHeader data={buildData(50)} project={project} />,
    )

    rerender(<AnalyzeHeader data={buildData(20)} project={project} />)

    const wordCountBox = screen.getByTestId('word-count-box')
    expect(wordCountBox).toHaveClass('updated-count')

    act(() => {
      jest.advanceTimersByTime(400)
    })

    expect(wordCountBox).not.toHaveClass('updated-count')
  })

  test('does not flash when the saving percentage stays the same', () => {
    const {rerender} = render(
      <AnalyzeHeader data={buildData(50)} project={project} />,
    )

    rerender(<AnalyzeHeader data={buildData(50)} project={project} />)

    expect(screen.getByTestId('word-count-box')).not.toHaveClass(
      'updated-count',
    )
  })
})
