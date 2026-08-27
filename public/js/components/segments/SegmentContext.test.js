import React, {useContext} from 'react'
import {render, screen} from '@testing-library/react'

import {SegmentContext} from './SegmentContext'

const Probe = () => {
  const value = useContext(SegmentContext)
  return <span data-testid="probe">{JSON.stringify(value)}</span>
}

test('defaults to an empty object with no provider', () => {
  render(<Probe />)
  expect(screen.getByTestId('probe').textContent).toBe('{}')
})

test('exposes the value supplied by a provider', () => {
  render(
    <SegmentContext.Provider value={{segment: {sid: '7'}}}>
      <Probe />
    </SegmentContext.Provider>,
  )
  expect(screen.getByTestId('probe').textContent).toBe('{"segment":{"sid":"7"}}')
})
