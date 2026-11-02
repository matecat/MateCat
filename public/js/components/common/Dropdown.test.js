import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {Dropdown} from './Dropdown'

// Every row counts as truncated, so hovering one always asks for a tooltip.
jest.mock('../../utils/textUtils', () => ({
  isContentTextEllipsis: jest.fn(() => true),
}))

const renderDropdown = (options) =>
  render(<Dropdown wrapper={{current: document.body}} options={options} />)

describe('Dropdown row tooltip', () => {
  test('shows the name of a truncated row', () => {
    renderDropdown([{id: '1', name: 'TRANSLATED'}])

    fireEvent.mouseEnter(screen.getByRole('listitem'))

    expect(screen.getByLabelText('TRANSLATED')).toHaveClass('dropdown__tooltip')
  })

  test('shows the text of a row whose name is markup', () => {
    renderDropdown([
      {
        id: '1',
        name: (
          <>
            <div className="status-dot translated-color" />
            TRANSLATED
          </>
        ),
      },
    ])

    fireEvent.mouseEnter(screen.getByRole('listitem'))

    expect(screen.getByLabelText('TRANSLATED')).toHaveClass('dropdown__tooltip')
    expect(screen.queryByLabelText('[object Object]')).not.toBeInTheDocument()
  })

  test('hides the tooltip when the pointer leaves the row', () => {
    renderDropdown([{id: '1', name: 'TRANSLATED'}])
    const row = screen.getByRole('listitem')

    fireEvent.mouseEnter(row)
    fireEvent.mouseLeave(row)

    expect(screen.queryByLabelText('TRANSLATED')).not.toBeInTheDocument()
  })
})

describe('Dropdown search bar', () => {
  test('renders the search icon and input when showSearchBar is true', () => {
    render(
      <Dropdown
        wrapper={{current: document.body}}
        options={[{id: '1', name: 'TRANSLATED'}]}
        showSearchBar
      />,
    )

    expect(screen.getByTestId('dropdown-search')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Search...')).toBeInTheDocument()
  })
})

describe('Dropdown imperative setListMaxHeight', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  test('applies the max height to the list after the deferred delay', () => {
    const ref = React.createRef()
    render(
      <Dropdown
        ref={ref}
        wrapper={{current: document.body}}
        options={[{id: '1', name: 'TRANSLATED'}]}
      />,
    )

    const list = ref.current.getListRef()
    // eslint-disable-next-line testing-library/no-node-access
    const listWrapper = list.parentElement

    ref.current.setListMaxHeight(200)

    // The outer wrapper fades in synchronously...
    // eslint-disable-next-line testing-library/no-node-access
    expect(listWrapper.parentElement).toHaveStyle({opacity: '1'})
    // ...but the max-height itself is only applied after the deferred delay.
    expect(list).toHaveStyle({maxHeight: ''})

    jest.advanceTimersByTime(40)

    expect(list).toHaveStyle({maxHeight: '200px'})
    expect(listWrapper).toHaveStyle({opacity: '1', height: 'auto'})
  })

  test('does not throw when the dropdown unmounts before the deferred delay fires', () => {
    const ref = React.createRef()
    const {unmount} = render(
      <Dropdown
        ref={ref}
        wrapper={{current: document.body}}
        options={[{id: '1', name: 'TRANSLATED'}]}
      />,
    )

    ref.current.setListMaxHeight(200)
    unmount()

    expect(() => jest.advanceTimersByTime(40)).not.toThrow()
  })
})
