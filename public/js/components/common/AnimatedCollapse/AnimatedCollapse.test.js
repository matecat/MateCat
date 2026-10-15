import React from 'react'
import {act, render, screen} from '@testing-library/react'
import {AnimatedCollapse} from './AnimatedCollapse'

global.ResizeObserver = class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

const dispatchTransitionEnd = (element, propertyName = 'height') => {
  act(() => {
    const event = new Event('transitionend', {bubbles: true})
    event.propertyName = propertyName
    element.dispatchEvent(event)
  })
}

describe('AnimatedCollapse', () => {
  it('does not render children when collapsed and never expanded', () => {
    render(
      <AnimatedCollapse expanded={false}>
        <div>hidden content</div>
      </AnimatedCollapse>,
    )
    expect(screen.queryByText('hidden content')).not.toBeInTheDocument()
  })

  it('renders children immediately when expanded from the start', () => {
    render(
      <AnimatedCollapse expanded={true}>
        <div>visible content</div>
      </AnimatedCollapse>,
    )
    expect(screen.getByText('visible content')).toBeInTheDocument()
  })

  it('mounts children and animates the wrapper height when switching to expanded', () => {
    const {container, rerender} = render(
      <AnimatedCollapse expanded={false}>
        <div>revealed content</div>
      </AnimatedCollapse>,
    )
    const wrapper = container.firstChild
    const content = wrapper.firstChild
    Object.defineProperty(content, 'scrollHeight', {
      value: 120,
      configurable: true,
    })

    rerender(
      <AnimatedCollapse expanded={true}>
        <div>revealed content</div>
      </AnimatedCollapse>,
    )

    expect(screen.getByText('revealed content')).toBeInTheDocument()
    expect(wrapper.style.height).toBe('120px')
  })

  it('keeps rendering children while the close transition is in progress, then unmounts', () => {
    const {container, rerender} = render(
      <AnimatedCollapse expanded={true}>
        <div>closing content</div>
      </AnimatedCollapse>,
    )
    const wrapper = container.firstChild

    rerender(
      <AnimatedCollapse expanded={false}>
        <div>closing content</div>
      </AnimatedCollapse>,
    )

    // still mounted mid-transition so the collapse is visible
    expect(screen.getByText('closing content')).toBeInTheDocument()
    expect(wrapper.style.height).toBe('0px')

    dispatchTransitionEnd(wrapper)

    expect(screen.queryByText('closing content')).not.toBeInTheDocument()
  })

  it('ignores transitionend events bubbling up from other properties', () => {
    const {container, rerender} = render(
      <AnimatedCollapse expanded={true}>
        <div>content</div>
      </AnimatedCollapse>,
    )
    const wrapper = container.firstChild

    rerender(
      <AnimatedCollapse expanded={false}>
        <div>content</div>
      </AnimatedCollapse>,
    )

    dispatchTransitionEnd(wrapper, 'opacity')
    expect(screen.getByText('content')).toBeInTheDocument()

    dispatchTransitionEnd(wrapper, 'height')
    expect(screen.queryByText('content')).not.toBeInTheDocument()
  })

  it('applies a custom className alongside the wrapper class', () => {
    const {container} = render(
      <AnimatedCollapse expanded={false} className="extra-class">
        <div>content</div>
      </AnimatedCollapse>,
    )
    expect(container.firstChild).toHaveClass('extra-class')
  })

  it('does not throw when unmounted mid-transition', () => {
    const {unmount, rerender} = render(
      <AnimatedCollapse expanded={true}>
        <div>content</div>
      </AnimatedCollapse>,
    )
    rerender(
      <AnimatedCollapse expanded={false}>
        <div>content</div>
      </AnimatedCollapse>,
    )
    expect(() => unmount()).not.toThrow()
  })
})
