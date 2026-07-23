import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import CopyButton from './CopyButton'

describe('CopyButton', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    act(() => jest.runOnlyPendingTimers())
    jest.useRealTimers()
  })

  it('renders a button', () => {
    render(<CopyButton onCopy={() => {}} />)
    expect(screen.getByRole('button')).toBeInTheDocument()
  })

  it('does not show the "Copied to Clipboard!" feedback before being clicked', () => {
    render(<CopyButton onCopy={() => {}} />)
    expect(screen.queryByText('Copied to Clipboard!')).not.toBeInTheDocument()
  })

  it('calls onCopy when clicked', () => {
    const onCopy = jest.fn()
    render(<CopyButton onCopy={onCopy} />)
    fireEvent.click(screen.getByRole('button'))
    expect(onCopy).toHaveBeenCalled()
  })

  it('shows the "Copied to Clipboard!" feedback after being clicked', () => {
    render(<CopyButton onCopy={() => {}} />)
    fireEvent.click(screen.getByRole('button'))
    expect(screen.getByText('Copied to Clipboard!')).toBeInTheDocument()
  })

  it('hides the feedback again after the timeout elapses', () => {
    render(<CopyButton onCopy={() => {}} />)
    fireEvent.click(screen.getByRole('button'))
    expect(screen.getByText('Copied to Clipboard!')).toBeInTheDocument()

    act(() => jest.advanceTimersByTime(2000))

    expect(screen.queryByText('Copied to Clipboard!')).not.toBeInTheDocument()
  })

  it('does not throw when clicked without an onCopy prop', () => {
    render(<CopyButton />)
    expect(() => fireEvent.click(screen.getByRole('button'))).not.toThrow()
  })

  it('clears the pending timeout on unmount without throwing', () => {
    const {unmount} = render(<CopyButton onCopy={() => {}} />)
    fireEvent.click(screen.getByRole('button'))
    expect(() => unmount()).not.toThrow()
  })

  it('resets the feedback timer when clicked again before it expires', () => {
    render(<CopyButton onCopy={() => {}} />)
    fireEvent.click(screen.getByRole('button'))
    act(() => jest.advanceTimersByTime(1500))
    fireEvent.click(screen.getByRole('button'))
    act(() => jest.advanceTimersByTime(1500))
    // still showing feedback since the second click reset the 2000ms timer
    expect(screen.getByText('Copied to Clipboard!')).toBeInTheDocument()
  })
})
