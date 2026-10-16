import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {BuyTranslationDrawer} from './BuyTranslationDrawer'

const mockContainerProps = []
jest.mock('../../outsource/OutsourceContainer', () => {
  const MockOutsourceContainer = (props) => {
    mockContainerProps.push(props)
    return (
      <div className="outsource-container" data-testid="order-form">
        <button type="button">Confirm order</button>
        <button type="button" hidden>
          Hidden action
        </button>
        <span aria-hidden="true">
          <button type="button">Decorative action</button>
        </span>
        <span tabIndex="0" disabled>
          Disabled custom control
        </span>
        <button type="button" disabled>
          Disabled action
        </button>
      </div>
    )
  }
  return MockOutsourceContainer
})

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

const project = {id: 20, password: 'projpass', name: 'Analyzed project'}
const projectJob = {id: 102, password: 'frpass', outsource: null}

const renderDrawer = (props = {}) => {
  const onClose = jest.fn()
  const utils = render(
    <>
      <button type="button">Opener</button>
      <BuyTranslationDrawer
        open={true}
        onClose={onClose}
        project={project}
        projectJob={projectJob}
        title="en-US › fr-FR"
        subtitle="ID 102"
        weightedWords={100}
        {...props}
      />
    </>,
  )
  return {...utils, onClose}
}

describe('BuyTranslationDrawer', () => {
  beforeEach(() => {
    mockContainerProps.length = 0
  })

  test('renders nothing while closed', () => {
    renderDrawer({open: false})
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  test('opens as a labelled modal dialog with the focus on it', () => {
    renderDrawer()
    const dialog = screen.getByRole('dialog', {name: 'en-US › fr-FR'})
    expect(dialog).toHaveAttribute('aria-modal', 'true')
    expect(screen.getByText('ID 102')).toBeInTheDocument()
    expect(screen.getByRole('button', {name: 'Close'})).toHaveFocus()
  })

  test('renders the existing order form for the job', () => {
    renderDrawer()
    expect(screen.getByTestId('order-form')).toBeInTheDocument()

    const props = mockContainerProps.at(-1)
    expect(props.openOutsource).toBe(true)
    expect(props.extendedView).toBe(true)
    expect(props.showTranslatorBox).toBe(false)
    expect(props.standardWC).toBe(100)
    expect(props.job.get('id')).toBe(102)
    expect(props.job.get('password')).toBe('frpass')
    expect(props.project.get('id')).toBe(20)
  })

  test('escape closes it', () => {
    const {onClose} = renderDrawer()
    fireEvent.keyDown(document, {key: 'Escape'})
    expect(onClose).toHaveBeenCalledTimes(1)
  })

  test('the backdrop and the close button close it', () => {
    const {onClose} = renderDrawer()
    fireEvent.click(screen.getByTestId('buy-translation-backdrop'))
    fireEvent.click(screen.getByRole('button', {name: 'Close'}))
    expect(onClose).toHaveBeenCalledTimes(2)
  })

  test('a click on the drawer frame does not count as outside the form', () => {
    const {onClose} = renderDrawer()
    const {onClickOutside} = mockContainerProps.at(-1)

    onClickOutside({target: screen.getByText('ID 102')})
    expect(onClose).not.toHaveBeenCalled()

    onClickOutside({target: screen.getByTestId('buy-translation-backdrop')})
    expect(onClose).toHaveBeenCalledTimes(1)
  })

  test('tab cycles inside the dialog, past the controls it cannot reach', () => {
    renderDrawer()
    const close = screen.getByRole('button', {name: 'Close'})
    // The last reachable control: after it come hidden, aria-hidden and
    // disabled ones
    const last = screen.getByRole('button', {name: 'Confirm order'})

    last.focus()
    fireEvent.keyDown(document, {key: 'Tab'})
    expect(close).toHaveFocus()

    fireEvent.keyDown(document, {key: 'Tab', shiftKey: true})
    expect(last).toHaveFocus()
  })

  test('renders into the body and makes the rest of the page inert', () => {
    const {container, rerender, onClose} = renderDrawer()
    const dialog = screen.getByRole('dialog')
    expect(container).not.toContainElement(dialog)
    expect(container).toHaveAttribute('inert')

    rerender(
      <BuyTranslationDrawer
        open={false}
        onClose={onClose}
        project={project}
        projectJob={projectJob}
        title="en-US › fr-FR"
      />,
    )
    expect(container).not.toHaveAttribute('inert')
  })

  test('closing gives the focus back to the opener', () => {
    const page = (open) => (
      <>
        <button type="button">Opener</button>
        <BuyTranslationDrawer
          open={open}
          onClose={jest.fn()}
          project={project}
          projectJob={projectJob}
          title="en-US › fr-FR"
        />
      </>
    )
    const {rerender} = render(page(false))
    const opener = screen.getByRole('button', {name: 'Opener'})
    opener.focus()

    rerender(page(true))
    expect(opener).not.toHaveFocus()

    rerender(page(false))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(opener).toHaveFocus()
  })
})
