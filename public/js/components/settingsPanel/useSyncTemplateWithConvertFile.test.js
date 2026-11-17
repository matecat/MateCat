import {renderHook, act, waitFor} from '@testing-library/react'
import useSyncTemplateWithConvertFile from './useSyncTemplateWithConvertFile'

global.config = {...global.config, is_cattool: true}

const defaultTemplate = {id: 0, name: 'default'}

const baseProps = () => ({
  currentTemplate: undefined,
  setTemplates: jest.fn(),
  defaultTemplate,
  idProjectTemplate: 1,
  idTemplate: 0,
  getTemplates: jest.fn(() => Promise.resolve({items: []})),
  checkIfUpdate: jest.fn(),
})

describe('useSyncTemplateWithConvertFile', () => {
  describe('when isCattool is true (default from config)', () => {
    test('does not retrieve templates', () => {
      const props = baseProps()
      renderHook(() => useSyncTemplateWithConvertFile(props))
      expect(props.getTemplates).not.toHaveBeenCalled()
    })

    test('does not select template on idTemplate/idProjectTemplate change', () => {
      const props = baseProps()
      const {rerender} = renderHook(
        (p) => useSyncTemplateWithConvertFile(p),
        {initialProps: props},
      )
      rerender({...props, idTemplate: 5})
      expect(props.setTemplates).not.toHaveBeenCalled()
    })

    test('does not check for updates when currentTemplate changes', () => {
      const props = baseProps()
      const {rerender} = renderHook(
        (p) => useSyncTemplateWithConvertFile(p),
        {initialProps: props},
      )
      rerender({...props, currentTemplate: {id: 1, name: 'a'}})
      expect(props.checkIfUpdate).not.toHaveBeenCalled()
    })
  })

  describe('when isCattool is explicitly false', () => {
    test('retrieves templates once and normalizes them, selecting the matching one', async () => {
      const items = [
        {id: 1, name: 'one', tm: null},
        {id: 2, name: 'two', tm: null},
      ]
      const props = {
        ...baseProps(),
        isCattool: false,
        idTemplate: 2,
        getTemplates: jest.fn(() => Promise.resolve({items})),
      }

      await act(async () => {
        renderHook(() => useSyncTemplateWithConvertFile(props))
      })

      expect(props.getTemplates).toHaveBeenCalledTimes(1)
      // the second effect (selection sync) also calls setTemplates with an
      // updater function on mount, before the retrieval promise resolves
      const arrayCall = props.setTemplates.mock.calls.find(
        ([arg]) => Array.isArray(arg),
      )
      const normalized = arrayCall[0]
      expect(normalized).toHaveLength(3)
      expect(normalized.find(({id}) => id === 2).isSelected).toBe(true)
      expect(normalized.find(({id}) => id === 1).isSelected).toBe(false)
      expect(normalized.find(({id}) => id === 0).isSelected).toBe(false)
    })

    test('normalizes a nested inner_content_type against the default template', async () => {
      const defaultTemplateWithContentType = {
        id: 0,
        name: 'default',
        json: {inner_content_type: 'text/plain', extract_arrays: false},
        yaml: {inner_content_type: 'text/plain'},
      }
      const items = [
        {
          id: 1,
          name: 'one',
          json: {inner_content_type: null, extract_arrays: true},
        },
        {id: 2, name: 'two', json: {inner_content_type: 'text/html'}},
      ]
      const props = {
        ...baseProps(),
        isCattool: false,
        defaultTemplate: defaultTemplateWithContentType,
        getTemplates: jest.fn(() => Promise.resolve({items})),
      }

      renderHook(() => useSyncTemplateWithConvertFile(props))
      await waitFor(() =>
        expect(
          props.setTemplates.mock.calls.some(([arg]) => Array.isArray(arg)),
        ).toBe(true),
      )

      const arrayCall = props.setTemplates.mock.calls.find(([arg]) =>
        Array.isArray(arg),
      )
      const normalized = arrayCall[0]

      // null inner_content_type falls back to the default template's value,
      // while other nested props on the same template stay untouched
      const templateOne = normalized.find(({id}) => id === 1)
      expect(templateOne.json.inner_content_type).toBe('text/plain')
      expect(templateOne.json.extract_arrays).toBe(true)

      // an explicit inner_content_type is kept as-is, not overwritten by the default
      expect(normalized.find(({id}) => id === 2).json.inner_content_type).toBe(
        'text/html',
      )

      // the default template itself is unaffected
      expect(normalized.find(({id}) => id === 0).json.inner_content_type).toBe(
        'text/plain',
      )
    })

    test('does not crash when a template has a null top-level prop', async () => {
      const items = [{id: 1, name: 'one', tm: null}]
      const props = {
        ...baseProps(),
        isCattool: false,
        getTemplates: jest.fn(() => Promise.resolve({items})),
      }

      renderHook(() => useSyncTemplateWithConvertFile(props))
      await waitFor(() =>
        expect(
          props.setTemplates.mock.calls.some(([arg]) => Array.isArray(arg)),
        ).toBe(true),
      )

      const arrayCall = props.setTemplates.mock.calls.find(([arg]) =>
        Array.isArray(arg),
      )
      expect(arrayCall[0].find(({id}) => id === 1).tm).toBeNull()
    })

    test('only retrieves templates once across rerenders', async () => {
      const props = {
        ...baseProps(),
        isCattool: false,
      }

      const {rerender} = renderHook(
        (p) => useSyncTemplateWithConvertFile(p),
        {initialProps: props},
      )

      await act(async () => {
        rerender({...props, idTemplate: 3})
      })

      expect(props.getTemplates).toHaveBeenCalledTimes(1)
    })

    test('re-selects the template whose id matches idTemplate on change, marking non-temporary ones', () => {
      const props = {
        ...baseProps(),
        isCattool: false,
        idTemplate: 7,
      }

      const {rerender} = renderHook(
        (p) => useSyncTemplateWithConvertFile(p),
        {initialProps: props},
      )

      const updater = props.setTemplates.mock.calls[0][0]
      const prevState = [
        {id: 0, isTemporary: false},
        {id: 7, isTemporary: false},
        {id: 8, isTemporary: true},
      ]
      const result = updater(prevState)
      expect(result).toEqual([
        {id: 0, isTemporary: false, isSelected: false},
        {id: 7, isTemporary: false, isSelected: true},
        {id: 8, isTemporary: true, isSelected: false},
      ])

      rerender({...props, idProjectTemplate: 99})
      expect(props.setTemplates).toHaveBeenCalledTimes(2)
    })

    test('calls checkIfUpdate with the filtered template when currentTemplate is not temporary', () => {
      const props = {
        ...baseProps(),
        isCattool: false,
      }
      const currentTemplate = {
        id: 1,
        name: 'a',
        uid: 10,
        isTemporary: false,
        isSelected: true,
        created_at: '2020',
        modified_at: '2021',
        tm: true,
      }

      renderHook((p) => useSyncTemplateWithConvertFile(p), {
        initialProps: {...props, currentTemplate},
      })

      expect(props.checkIfUpdate).toHaveBeenCalledWith({id: 1, tm: true})
    })

    test('does not call checkIfUpdate when currentTemplate is temporary', () => {
      const props = {
        ...baseProps(),
        isCattool: false,
      }
      const currentTemplate = {id: 1, isTemporary: true}

      renderHook((p) => useSyncTemplateWithConvertFile(p), {
        initialProps: {...props, currentTemplate},
      })

      expect(props.checkIfUpdate).not.toHaveBeenCalled()
    })

    test('does not call checkIfUpdate when there is no currentTemplate', () => {
      const props = {
        ...baseProps(),
        isCattool: false,
        currentTemplate: undefined,
      }

      renderHook((p) => useSyncTemplateWithConvertFile(p), {
        initialProps: props,
      })

      expect(props.checkIfUpdate).not.toHaveBeenCalled()
    })
  })
})
