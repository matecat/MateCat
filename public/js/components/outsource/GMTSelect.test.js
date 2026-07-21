import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import Cookies from 'js-cookie'
import {GMTSelect} from './GMTSelect'

jest.mock('../common/Select', () => ({
  Select: ({label, options, activeOption, onSelect}) => (
    <div>
      {label && <span>{label}</span>}
      <span data-testid="active-option">{activeOption?.name}</span>
      {options.map((option) => (
        <button key={option.id} onClick={() => onSelect(option)}>
          {option.name}
        </button>
      ))}
    </div>
  ),
}))

describe('GMTSelect', () => {
  afterEach(() => {
    Cookies.remove('matecat_timezone')
  })

  test('shows the GMT label when showLabel is true', () => {
    render(<GMTSelect showLabel={true} changeValue={jest.fn()} />)
    expect(screen.getByText('GMT')).toBeInTheDocument()
  })

  test('hides the label when showLabel is false', () => {
    render(<GMTSelect showLabel={false} changeValue={jest.fn()} />)
    expect(screen.queryByText('GMT')).not.toBeInTheDocument()
  })

  test('defaults to the first GMT option when no cookie is set', () => {
    render(<GMTSelect changeValue={jest.fn()} />)
    expect(screen.getByTestId('active-option')).toHaveTextContent(
      '(GMT -11:00 ) Midway Islands Time',
    )
  })

  test('uses the stored cookie timezone as the active option', () => {
    Cookies.set('matecat_timezone', '2')
    render(<GMTSelect changeValue={jest.fn()} />)
    expect(screen.getByTestId('active-option')).toHaveTextContent(
      '(GMT +2:00 ) Eastern European Time',
    )
  })

  test('calls changeValue and updates the active option when a new zone is selected', () => {
    const changeValue = jest.fn()
    render(<GMTSelect changeValue={changeValue} />)

    fireEvent.click(screen.getByText('(GMT) Greenwich Mean Time'))

    expect(changeValue).toHaveBeenCalledWith('0')
    expect(screen.getByTestId('active-option')).toHaveTextContent(
      '(GMT) Greenwich Mean Time',
    )
  })
})
