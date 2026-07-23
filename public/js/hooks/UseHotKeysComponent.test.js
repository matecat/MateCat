import {renderHook} from '@testing-library/react'
import {useHotkeys} from 'react-hotkeys-hook'
import {UseHotKeysComponent} from './UseHotKeysComponent'

jest.mock('react-hotkeys-hook', () => ({
  useHotkeys: jest.fn(),
}))

describe('UseHotKeysComponent', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('registers the shortcut with the default options', () => {
    const callback = jest.fn()
    renderHook(() => UseHotKeysComponent({shortcut: 'ctrl+s', callback}))
    expect(useHotkeys).toHaveBeenCalledWith('ctrl+s', callback, {
      keyup: false,
      enableOnContentEditable: true,
      enableOnFormTags: true,
    })
  })

  test('registers the shortcut with custom options', () => {
    const callback = jest.fn()
    renderHook(() =>
      UseHotKeysComponent({
        shortcut: 'esc',
        callback,
        keyup: true,
        enableOnContentEditable: false,
        enableOnFormTags: false,
      }),
    )
    expect(useHotkeys).toHaveBeenCalledWith('esc', callback, {
      keyup: true,
      enableOnContentEditable: false,
      enableOnFormTags: false,
    })
  })
})
