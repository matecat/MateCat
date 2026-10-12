import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import ReviewExtendedCategorySelector from './ReviewExtendedCategorySelector'

jest.mock('../common/DropdownMenu/DropdownMenu', () => ({
  DROPDOWN_MENU_ALIGN: {RIGHT: 'right'},
  DropdownMenu: ({items}) => (
    <div data-testid="dropdown-menu">
      {items.map((item) => (
        <button key={item.label} onClick={item.onClick}>
          {item.label}
        </button>
      ))}
    </div>
  ),
}))

const makeCategory = (overrides = {}) => ({
  id: '1',
  label: 'Accuracy',
  severities: [{label: 'MINOR'}, {label: 'MAJOR'}],
  ...overrides,
})

describe('ReviewExtendedCategorySelector', () => {
  test('renders the category label', () => {
    render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory()}
      />,
    )
    expect(screen.getByText('Accuracy')).toBeInTheDocument()
  })

  test('renders a truncated (first 3 chars) label when multiple severities exist and no code', () => {
    render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory()}
      />,
    )
    expect(screen.getByRole('button', {name: 'MIN'})).toBeInTheDocument()
    expect(screen.getByRole('button', {name: 'MAJ'})).toBeInTheDocument()
  })

  test('renders the full label when there is only one severity', () => {
    render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory({severities: [{label: 'MINOR'}]})}
      />,
    )
    expect(screen.getByRole('button', {name: 'MINOR'})).toBeInTheDocument()
  })

  test('renders the severity code instead of the truncated label when code is present', () => {
    render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory({severities: [{label: 'MINOR', code: 'MN'}]})}
      />,
    )
    expect(screen.getByRole('button', {name: 'MN'})).toBeInTheDocument()
  })

  test('clicking a severity button calls sendIssue with the category and severity label', () => {
    const sendIssue = jest.fn()
    const category = makeCategory()
    render(
      <ReviewExtendedCategorySelector
        sendIssue={sendIssue}
        category={category}
      />,
    )
    fireEvent.click(screen.getByRole('button', {name: 'MIN'}))
    expect(sendIssue).toHaveBeenCalledWith(category, 'MINOR')
  })

  test('applies the active class to the button matching severityActiveIndex when active', () => {
    render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory()}
        active={true}
        severityActiveIndex={1}
      />,
    )
    expect(screen.getByRole('button', {name: 'MAJ'})).toHaveClass('active')
    expect(screen.getByRole('button', {name: 'MIN'})).not.toHaveClass('active')
  })

  test('does not apply the active class to severity buttons when active is false', () => {
    render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory()}
        active={false}
        severityActiveIndex={0}
      />,
    )
    expect(screen.getByRole('button', {name: 'MIN'})).not.toHaveClass('active')
  })

  test('renders a DropdownMenu instead of buttons when there are more than 7 severities', () => {
    const manySeverities = Array.from({length: 8}, (_, i) => ({
      label: `SEV${i}`,
    }))
    render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory({severities: manySeverities})}
      />,
    )
    expect(screen.getByTestId('dropdown-menu')).toBeInTheDocument()
  })

  test('clicking a DropdownMenu item calls sendIssue with the category and severity label', () => {
    const sendIssue = jest.fn()
    const manySeverities = Array.from({length: 8}, (_, i) => ({
      label: `SEV${i}`,
    }))
    const category = makeCategory({severities: manySeverities})
    render(
      <ReviewExtendedCategorySelector
        sendIssue={sendIssue}
        category={category}
      />,
    )
    fireEvent.click(screen.getByText('SEV0'))
    expect(sendIssue).toHaveBeenCalledWith(category, 'SEV0')
  })

  test('applies the active class to the container when active prop is true', () => {
    const {container} = render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory()}
        active={true}
      />,
    )
    expect(container.querySelector('.re-item.re-category-item')).toHaveClass(
      'active',
    )
  })

  test('does not apply the severity-buttons class when the category has no severities', () => {
    const {container} = render(
      <ReviewExtendedCategorySelector
        sendIssue={jest.fn()}
        category={makeCategory({severities: []})}
      />,
    )
    expect(
      container.querySelector('.re-item.re-category-item'),
    ).not.toHaveClass('severity-buttons')
  })
})
