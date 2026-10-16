import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import Cookies from 'js-cookie'
import OutsourceContainer from './OutsourceContainer'

jest.mock('./AssignToTranslator', () => () => <div>assign-to-translator</div>)

const baseProps = {
  showTranslatorBox: true,
  idJobLabel: 'job-1',
  job: {},
  project: {},
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
      <OutsourceContainer {...baseProps} showTranslatorBox={false} />,
    )
    expect(container.querySelector('.outsource-container')).toBeNull()
  })

  test('renders the translator assignment box when showTranslatorBox is true', () => {
    render(<OutsourceContainer {...baseProps} />)
    expect(screen.getByText('assign-to-translator')).toBeInTheDocument()
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
