import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import OutsourceInfo from './OutsourceInfo'

describe('OutsourceInfo', () => {
  test('renders the testimonials slider and highlights the first slide', () => {
    const {container} = render(<OutsourceInfo />)

    expect(screen.getByText('Have a specific request?')).toBeInTheDocument()
    const items = container.getElementsByClassName('customer-box-info')
    expect(items.length).toBe(4)
    expect(items[0]).toHaveClass('fade-in')
  })

  test('clicking a pointer moves the slider to that testimonial', () => {
    const {container} = render(<OutsourceInfo />)
    const pointers = container.getElementsByClassName('pointer')
    const items = container.getElementsByClassName('customer-box-info')

    fireEvent.click(pointers[2])

    expect(items[2]).toHaveClass('fade-in')
    expect(pointers[2]).toHaveClass('active')
    expect(items[0]).not.toHaveClass('fade-in')
  })

  test('auto-advances and wraps around to the first slide after the last one', () => {
    jest.useFakeTimers()
    const {container} = render(<OutsourceInfo />)
    const items = container.getElementsByClassName('customer-box-info')

    // Mount already showed item 0 (index 1). Advancing the 6s timer three
    // more times reaches the last item (index 4), and a fourth advance
    // wraps the counter back around to the first slide.
    for (let i = 0; i < 4; i++) {
      jest.advanceTimersByTime(6000)
    }

    expect(items[0]).toHaveClass('fade-in')
    jest.useRealTimers()
  })

  test('dispatches the openChat custom event when the chat button is clicked', () => {
    render(<OutsourceInfo />)
    const listener = jest.fn()
    document.addEventListener('openChat', listener)

    fireEvent.click(screen.getByText('Open chat'))

    expect(listener).toHaveBeenCalledTimes(1)
    document.removeEventListener('openChat', listener)
  })

  test('clears the slider timeout on unmount', () => {
    const {unmount} = render(<OutsourceInfo />)
    expect(() => unmount()).not.toThrow()
  })
})
