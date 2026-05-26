import React from 'react'
import {renderHook, waitFor} from '@testing-library/react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import useOptions from './useOptions'

jest.mock('react-hook-form', () => ({
  useForm: jest.fn(),
}))

describe('useOptions', () => {
  let watchValues
  let setValueMock
  let modifyingCurrentTemplateMock
  let currentProjectTemplate

  const wrapper = ({children}) => (
    <SettingsPanelContext.Provider
      value={{
        currentProjectTemplate,
        modifyingCurrentTemplate: modifyingCurrentTemplateMock,
      }}
    >
      {children}
    </SettingsPanelContext.Provider>
  )

  beforeEach(() => {
    const {useForm} = require('react-hook-form')

    watchValues = {}
    setValueMock = jest.fn()
    modifyingCurrentTemplateMock = jest.fn()
    currentProjectTemplate = {
      mt: {
        id: 10,
        extra: {
          lara_style: 'faithful',
          enable_mt_analysis: true,
        },
      },
    }

    useForm.mockReturnValue({
      control: {name: 'control'},
      watch: jest.fn(() => watchValues),
      setValue: setValueMock,
    })
  })

  afterEach(() => jest.clearAllMocks())

  test('returns control, setValue and watch from useForm', () => {
    const {result} = renderHook(() => useOptions(), {wrapper})

    expect(result.current.control).toEqual({name: 'control'})
    expect(result.current.setValue).toBe(setValueMock)
    expect(typeof result.current.watch).toBe('function')
  })

  test('sets default values from currentProjectTemplate.mt.extra', async () => {
    renderHook(() => useOptions(), {wrapper})

    await waitFor(() => {
      expect(setValueMock).toHaveBeenCalledWith('lara_style', 'faithful')
      expect(setValueMock).toHaveBeenCalledWith('enable_mt_analysis', true)
    })
  })

  test('excludes fields listed in excludedFields from template update', async () => {
    watchValues = {
      lara_style: 'fluid',
      lara_style_guide: 'sg1',
      ignored_field: 'do-not-apply',
    }

    renderHook(() => useOptions(['ignored_field']), {wrapper})

    await waitFor(() => {
      expect(modifyingCurrentTemplateMock).toHaveBeenCalledTimes(1)
    })

    const updater = modifyingCurrentTemplateMock.mock.calls[0][0]
    const updated = updater({
      mt: {
        extra: {
          lara_style: 'faithful',
          another: 'value',
        },
      },
    })

    expect(updated.mt.extra).toEqual({
      lara_style: 'fluid',
      lara_style_guide: 'sg1',
      another: 'value',
    })
    expect(updated.mt.extra.ignored_field).toBeUndefined()
  })

  test('does not update template when filtered form data equals mt.extra', async () => {
    watchValues = {
      lara_style: 'faithful',
      enable_mt_analysis: true,
    }

    renderHook(() => useOptions(), {wrapper})

    await waitFor(() => {
      expect(setValueMock).toHaveBeenCalled()
    })

    expect(modifyingCurrentTemplateMock).not.toHaveBeenCalled()
  })

  test('removes undefined values from previous extra before merging', async () => {
    watchValues = {
      lara_style: 'creative',
    }

    renderHook(() => useOptions(), {wrapper})

    await waitFor(() => {
      expect(modifyingCurrentTemplateMock).toHaveBeenCalledTimes(1)
    })

    const updater = modifyingCurrentTemplateMock.mock.calls[0][0]
    const updated = updater({
      mt: {
        extra: {
          stale: undefined,
          keep: 'ok',
        },
      },
    })

    expect(updated.mt.extra).toEqual({
      keep: 'ok',
      lara_style: 'creative',
    })
    expect(updated.mt.extra.stale).toBeUndefined()
  })
})
