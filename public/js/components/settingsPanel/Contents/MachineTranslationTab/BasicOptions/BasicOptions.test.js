import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {BasicOptions} from './BasicOptions'
import useOptions from '../useOptions'

jest.mock('../useOptions', () =>
  jest.fn(() => ({
    control: {},
  })),
)

jest.mock('react-hook-form', () => ({
  Controller: ({render: renderProp, name, control, disabled}) =>
    renderProp({
      field: {
        onChange: jest.fn(),
        value: false,
        name,
        disabled: disabled ?? false,
      },
    }),
}))

jest.mock('../../../../common/Switch', () => ({
  __esModule: true,
  default: ({name, active, onChange, disabled}) => (
    <input
      type="checkbox"
      name={name}
      checked={!!active}
      onChange={onChange}
      disabled={disabled}
      data-testid={`switch-${name}`}
    />
  ),
}))

describe('BasicOptions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    useOptions.mockReturnValue({control: {}})
  })

  test('renders the pre-translate section with a switch', () => {
    render(<BasicOptions isCattoolPage={false} />)

    expect(screen.getByText('Pre-translate files')).toBeInTheDocument()
    expect(screen.getByTestId('switch-enable_mt_analysis')).toBeInTheDocument()
  })

  test('disables the switch on cattool page', () => {
    render(<BasicOptions isCattoolPage={true} />)

    expect(screen.getByTestId('switch-enable_mt_analysis')).toBeDisabled()
  })

  test('enables the switch when not on cattool page', () => {
    render(<BasicOptions isCattoolPage={false} />)

    expect(screen.getByTestId('switch-enable_mt_analysis')).not.toBeDisabled()
  })

  test('forwards onChange interaction to the switch', () => {
    render(<BasicOptions isCattoolPage={false} />)

    const switchEl = screen.getByTestId('switch-enable_mt_analysis')
    expect(() => fireEvent.click(switchEl)).not.toThrow()
  })
})
