import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import Cookies from 'js-cookie'
import OutsourceContainer from './OutsourceContainer'

jest.mock('./AssignToTranslator', () => () => <div>assign-to-translator</div>)
jest.mock('./OutsourceVendor', () => () => <div>outsource-vendor</div>)

const baseProps = {
  openOutsource: false,
  showTranslatorBox: true,
  idJobLabel: 'job-1',
  job: {},
  standardWC: 100,
  project: {},
  extendedView: true,
  onClickOutside: jest.fn(),
}

beforeAll(() => {
  Element.prototype.scrollIntoView = jest.fn()
})

describe('OutsourceContainer', () => {
  beforeEach(() => {
    Cookies.remove('matecat_timezone')
    global.config = {...global.config, enable_outsource: 1}
  })

  test('sets a matecat_timezone cookie on mount when none exists', () => {
    const setSpy = jest.spyOn(Cookies, 'set')
    render(<OutsourceContainer {...baseProps} />)
    expect(setSpy).toHaveBeenCalledWith(
      'matecat_timezone',
      expect.anything(),
      expect.objectContaining({secure: true}),
    )
    setSpy.mockRestore()
  })

  test('does not override an existing matecat_timezone cookie', () => {
    Cookies.set('matecat_timezone', '5')
    const setSpy = jest.spyOn(Cookies, 'set')
    render(<OutsourceContainer {...baseProps} />)
    expect(setSpy).not.toHaveBeenCalled()
    setSpy.mockRestore()
  })

  test('renders nothing when both openOutsource and showTranslatorBox are false', () => {
    const {container} = render(
      <OutsourceContainer
        {...baseProps}
        showTranslatorBox={false}
        openOutsource={false}
      />,
    )
    expect(container.querySelector('.outsource-container')).toBeNull()
  })

  test('renders the translator assignment box when showTranslatorBox is true', () => {
    render(<OutsourceContainer {...baseProps} />)
    expect(screen.getByText('assign-to-translator')).toBeInTheDocument()
    expect(screen.queryByText('outsource-vendor')).not.toBeInTheDocument()
  })

  test('renders the outsource vendor box when openOutsource is true and outsourcing is enabled', () => {
    render(
      <OutsourceContainer
        {...baseProps}
        showTranslatorBox={false}
        openOutsource={true}
      />,
    )
    expect(screen.getByText('outsource-vendor')).toBeInTheDocument()
  })

  test('does not render the outsource vendor box when outsourcing is disabled globally', () => {
    global.config = {...global.config, enable_outsource: 0}
    render(
      <OutsourceContainer
        {...baseProps}
        showTranslatorBox={false}
        openOutsource={true}
      />,
    )
    expect(screen.queryByText('outsource-vendor')).not.toBeInTheDocument()
  })

  test('renders both boxes when showTranslatorBox and openOutsource are both true', () => {
    render(
      <OutsourceContainer
        {...baseProps}
        showTranslatorBox={true}
        openOutsource={true}
      />,
    )
    expect(screen.getByText('assign-to-translator')).toBeInTheDocument()
    expect(screen.getByText('outsource-vendor')).toBeInTheDocument()
  })

  test('calls onClickOutside when a mousedown happens outside the container', () => {
    jest.useFakeTimers()
    const onClickOutside = jest.fn()
    render(
      <OutsourceContainer {...baseProps} onClickOutside={onClickOutside} />,
    )

    jest.advanceTimersByTime(500)
    jest.useRealTimers()

    fireEvent.mouseDown(document.body)
    expect(onClickOutside).toHaveBeenCalled()
  })

  test('does not call onClickOutside for clicks on ignored classes', () => {
    jest.useFakeTimers()
    const onClickOutside = jest.fn()
    render(
      <OutsourceContainer {...baseProps} onClickOutside={onClickOutside} />,
    )
    jest.advanceTimersByTime(500)
    jest.useRealTimers()

    const ignoredEl = document.createElement('div')
    ignoredEl.className = 'faster'
    document.body.appendChild(ignoredEl)

    fireEvent.mouseDown(ignoredEl)
    expect(onClickOutside).not.toHaveBeenCalled()

    document.body.removeChild(ignoredEl)
  })

  test('calls onClickOutside when the Escape key is pressed', () => {
    jest.useFakeTimers()
    const onClickOutside = jest.fn()
    render(
      <OutsourceContainer {...baseProps} onClickOutside={onClickOutside} />,
    )
    jest.advanceTimersByTime(500)
    jest.useRealTimers()

    fireEvent.keyDown(document, {keyCode: 27})
    expect(onClickOutside).toHaveBeenCalled()
  })

  test('removes listeners on unmount without throwing', () => {
    jest.useFakeTimers()
    const {unmount} = render(<OutsourceContainer {...baseProps} />)
    jest.advanceTimersByTime(500)
    jest.useRealTimers()
    expect(() => unmount()).not.toThrow()
  })
})
