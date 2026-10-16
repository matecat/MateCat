import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {OutsourceDrawer} from './OutsourceDrawer'

// jsdom does no layout: offsetParent is null for everything. Rendered
// elements get the body, hidden ones (and their content) null
const offsetParent = Object.getOwnPropertyDescriptor(
  HTMLElement.prototype,
  'offsetParent',
)
beforeAll(() => {
  Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
    configurable: true,
    get() {
      return this.matches('[hidden], [hidden] *') ? null : document.body
    },
  })
})
afterAll(() => {
  Object.defineProperty(HTMLElement.prototype, 'offsetParent', offsetParent)
})

const content = (
  <div>
    <button type="button">Order now</button>
    <button type="button" hidden>
      Hidden action
    </button>
    <span aria-hidden="true">
      <button type="button">Decorative action</button>
    </span>
    <button type="button" disabled>
      Disabled action
    </button>
  </div>
)

const renderDrawer = (props = {}) => {
  const onClose = jest.fn()
  const utils = render(
    <OutsourceDrawer
      open={true}
      onClose={onClose}
      item="en-US › fr-FR · 59 Matecat weighted words"
      {...props}
    >
      {content}
    </OutsourceDrawer>,
  )
  return {...utils, onClose}
}

describe('OutsourceDrawer', () => {
  test('opens as a modal dialog with the focus inside it', () => {
    renderDrawer()
    expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true')
    expect(screen.getByRole('button', {name: 'Close'})).toHaveFocus()
  })

  test('escape, the backdrop and the close button close it', () => {
    const {onClose} = renderDrawer()
    fireEvent.keyDown(document, {key: 'Escape'})
    fireEvent.click(screen.getByTestId('buy-translation-backdrop'))
    fireEvent.click(screen.getByRole('button', {name: 'Close'}))
    expect(onClose).toHaveBeenCalledTimes(3)
  })

  test('tab cycles inside, past hidden, aria-hidden and disabled controls', () => {
    renderDrawer()
    const close = screen.getByRole('button', {name: 'Close'})
    const last = screen.getByRole('button', {name: 'Order now'})

    last.focus()
    fireEvent.keyDown(document, {key: 'Tab'})
    expect(close).toHaveFocus()

    fireEvent.keyDown(document, {key: 'Tab', shiftKey: true})
    expect(last).toHaveFocus()
  })

  test('renders into the body, makes the page inert, then gives the focus back', () => {
    const page = (open) => (
      <>
        <button type="button">Opener</button>
        <OutsourceDrawer open={open} onClose={jest.fn()}>
          {content}
        </OutsourceDrawer>
      </>
    )
    const {container, rerender} = render(page(false))
    const opener = screen.getByRole('button', {name: 'Opener'})
    opener.focus()

    rerender(page(true))
    expect(container).not.toContainElement(screen.getByRole('dialog'))
    expect(container).toHaveAttribute('inert')
    expect(opener).not.toHaveFocus()

    rerender(page(false))
    expect(container).not.toHaveAttribute('inert')
    expect(opener).toHaveFocus()
  })

  test('escape inside a menu or a calendar leaves the drawer open', () => {
    const onClose = jest.fn()
    render(
      <OutsourceDrawer open={true} onClose={onClose}>
        <div role="menu">
          <button type="button">EUR</button>
        </div>
        <div className="react-datepicker">
          <button type="button">Day 12</button>
        </div>
      </OutsourceDrawer>,
    )
    fireEvent.keyDown(screen.getByRole('button', {name: 'EUR'}), {
      key: 'Escape',
    })
    fireEvent.keyDown(screen.getByRole('button', {name: 'Day 12'}), {
      key: 'Escape',
    })
    expect(onClose).not.toHaveBeenCalled()
  })
})
