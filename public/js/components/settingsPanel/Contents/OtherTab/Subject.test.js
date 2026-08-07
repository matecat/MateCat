import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {Subject} from './Subject'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

jest.mock('../../../common/Select', () => ({
  Select: ({onSelect, activeOption, options}) => (
    <div>
      <div data-testid="active-option">{activeOption?.name ?? ''}</div>
      {options?.map((opt) => (
        <button
          key={opt.id}
          data-testid={`option-${opt.id}`}
          onClick={() => onSelect(opt)}
        >
          {opt.name}
        </button>
      ))}
    </div>
  ),
}))

const renderSubject = (subject, setSubject = jest.fn()) =>
  render(
    <CreateProjectContext.Provider
      value={{SELECT_HEIGHT: 200, subject, setSubject}}
    >
      <Subject />
    </CreateProjectContext.Provider>,
  )

describe('Subject', () => {
  beforeEach(() => {
    global.config.subject_array = [
      {key: 'general', display: 'General'},
      {key: 'legal', display: 'Legal'},
    ]
  })

  test('renders heading and description', () => {
    renderSubject(undefined)
    expect(screen.getByText('Subject')).toBeInTheDocument()
    expect(
      screen.getByText("Select your project's subject."),
    ).toBeInTheDocument()
  })

  test('maps config.subject_array items to id/name options', () => {
    renderSubject(undefined)
    expect(screen.getByTestId('option-general')).toHaveTextContent('General')
    expect(screen.getByTestId('option-legal')).toHaveTextContent('Legal')
  })

  test('shows the current subject as active option', () => {
    renderSubject({key: 'legal', id: 'legal', name: 'Legal', display: 'Legal'})
    expect(screen.getByTestId('active-option').textContent).toBe('Legal')
  })

  test('selecting an option calls setSubject with the option', () => {
    const setSubject = jest.fn()
    renderSubject(undefined, setSubject)

    fireEvent.click(screen.getByTestId('option-general'))

    expect(setSubject).toHaveBeenCalledTimes(1)
    expect(setSubject).toHaveBeenCalledWith(
      expect.objectContaining({id: 'general', name: 'General'}),
    )
  })
})
