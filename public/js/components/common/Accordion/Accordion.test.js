import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {Accordion} from './Accordion'

global.ResizeObserver = class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

describe('Accordion', () => {
  it('renders the title', () => {
    render(
      <Accordion id="acc-1" title="My title">
        <div>content</div>
      </Accordion>,
    )
    expect(screen.getByText('My title')).toBeInTheDocument()
  })

  it('does not render children when not expanded and not yet clicked', () => {
    render(
      <Accordion id="acc-1" title="My title">
        <div>hidden content</div>
      </Accordion>,
    )
    expect(screen.queryByText('hidden content')).not.toBeInTheDocument()
  })

  it('renders children when expanded is true from the start', () => {
    render(
      <Accordion id="acc-1" title="My title" expanded>
        <div>visible content</div>
      </Accordion>,
    )
    expect(screen.getByText('visible content')).toBeInTheDocument()
  })

  it('sets data-expanded attribute when expanded', () => {
    const {container} = render(
      <Accordion id="acc-1" title="My title" expanded>
        <div>content</div>
      </Accordion>,
    )
    expect(container.querySelector('[data-expanded]')).toBeInTheDocument()
  })

  it('does not set data-expanded attribute when not expanded', () => {
    const {container} = render(
      <Accordion id="acc-1" title="My title">
        <div>content</div>
      </Accordion>,
    )
    expect(container.querySelector('[data-expanded]')).not.toBeInTheDocument()
  })

  it('calls onShow with the id and renders children after clicking the title', () => {
    const onShow = jest.fn()
    render(
      <Accordion id="acc-42" title="My title" onShow={onShow}>
        <div>revealed content</div>
      </Accordion>,
    )
    fireEvent.click(screen.getByText('My title'))
    expect(onShow).toHaveBeenCalledWith('acc-42')
    expect(screen.getByText('revealed content')).toBeInTheDocument()
  })

  it('applies a custom className to the outer wrapper', () => {
    const {container} = render(
      <Accordion id="acc-1" title="My title" className="extra-class">
        <div>content</div>
      </Accordion>,
    )
    expect(container.firstChild).toHaveClass('extra-class')
  })

  it('does not throw when unmounted while expanded', () => {
    const {unmount} = render(
      <Accordion id="acc-1" title="My title" expanded>
        <div>content</div>
      </Accordion>,
    )
    expect(() => unmount()).not.toThrow()
  })

  it('does not throw when unmounted while collapsed', () => {
    const {unmount} = render(
      <Accordion id="acc-1" title="My title">
        <div>content</div>
      </Accordion>,
    )
    expect(() => unmount()).not.toThrow()
  })
})
