import React from 'react'
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {IntentoOptions} from './IntentoOptions'
import useOptions from '../useOptions'
import {getIntentoRouting} from '../../../../../api/getIntentoRouting'

const mockControllerOnChange = {}
let mockSetValue

// --- Mocks ---

// `PROVIDERS` is computed from `config.intento_providers` at module load
// time, before any `beforeEach` runs, so it has to be seeded here: this
// factory runs while IntentoOptions.js's own imports are still resolving,
// ahead of its top-level `const PROVIDERS = ...` line.
jest.mock('../useOptions', () => {
  global.config = Object.assign(global.config ?? {}, {
    intento_providers: {p1: {id: 'provider1', name: 'Provider One'}},
  })
  return jest.fn(() => ({
    control: {},
    setValue: jest.fn(),
  }))
})

jest.mock('react-hook-form', () => ({
  Controller: ({render: renderProp, name, disabled}) => {
    const onChange = jest.fn()
    mockControllerOnChange[name] = onChange
    return renderProp({
      field: {
        onChange,
        value: undefined,
        name,
        disabled: disabled ?? false,
      },
    })
  },
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

jest.mock('../../../../common/Select', () => {
  const ReactLib = require('react')
  return {
    Select: ({name, options, activeOption, onSelect, isDisabled, children}) => (
      <div data-testid={`select-${name}`}>
        <span data-testid={`select-${name}-active`}>
          {activeOption?.id ?? ''}
        </span>
        {(options ?? []).map((opt) => {
          const {beforeRow} = children?.(opt) || {}
          return (
            <ReactLib.Fragment key={opt.id}>
              {beforeRow}
              <button
                data-testid={`option-${name}-${opt.id}`}
                disabled={isDisabled}
                onClick={() => onSelect(opt)}
              >
                {opt.id}
              </button>
            </ReactLib.Fragment>
          )
        })}
      </div>
    ),
  }
})

jest.mock('../../../../../api/getIntentoRouting', () => ({
  getIntentoRouting: jest.fn(() => Promise.resolve({})),
}))

// --- Helpers ---

const defaultTemplate = {
  mt: {
    id: 1,
    extra: {},
  },
}

const renderComponent = async ({
  id = 1,
  isCattoolPage = false,
  currentProjectTemplate = defaultTemplate,
} = {}) => {
  const utils = render(
    <SettingsPanelContext.Provider value={{currentProjectTemplate}}>
      <IntentoOptions id={id} isCattoolPage={isCattoolPage} />
    </SettingsPanelContext.Provider>,
  )
  // Flush the routings fetch (resolved or not yet awaited by the test) so
  // its state update lands inside act() instead of leaking into the next test.
  await act(async () => {
    await Promise.resolve()
  })
  return utils
}

beforeEach(() => {
  jest.clearAllMocks()
  getIntentoRouting.mockResolvedValue({})
  Object.keys(mockControllerOnChange).forEach(
    (key) => delete mockControllerOnChange[key],
  )
  mockSetValue = jest.fn()
  useOptions.mockReturnValue({
    control: {},
    setValue: mockSetValue,
  })
})

// --- Tests ---

describe('IntentoOptions', () => {
  test('renders Pre-translate and Provider/routing sections', async () => {
    await renderComponent()

    expect(screen.getByText('Pre-translate files')).toBeInTheDocument()
    expect(screen.getByText('Provider or routing')).toBeInTheDocument()
    expect(screen.getByTestId('select-intento_routing')).toBeInTheDocument()
  })

  test('fetches routings on mount, scoped to the given id', async () => {
    await renderComponent({id: 42})

    expect(getIntentoRouting).toHaveBeenCalledWith(42)
  })

  test('sorts fetched routings with smart_routing first and labels them with a heading', async () => {
    getIntentoRouting.mockResolvedValue({
      a: {name: 'routing_b'},
      b: {name: 'smart_routing'},
    })

    await renderComponent()

    await waitFor(() => {
      expect(
        screen.getByTestId('option-intento_routing-smart_routing'),
      ).toBeInTheDocument()
    })

    const routingsHeading = screen.getByText('Routings')
    expect(routingsHeading.tagName).toBe('H5')
    expect(
      screen.getByTestId('option-intento_routing-routing_b'),
    ).toBeInTheDocument()
  })

  test('labels the first provider option with a Providers heading', async () => {
    await renderComponent()

    const providersHeading = screen.getByText('Providers')
    expect(providersHeading.tagName).toBe('H5')
    expect(
      screen.getByTestId('option-intento_routing-provider1'),
    ).toBeInTheDocument()
  })

  test('uses intento_routing as the active field when no provider is set', async () => {
    await renderComponent()

    expect(screen.getByTestId('select-intento_routing')).toBeInTheDocument()
    expect(
      screen.queryByTestId('select-intento_provider'),
    ).not.toBeInTheDocument()
  })

  test('uses intento_provider as the active field when a provider is set', async () => {
    await renderComponent({
      currentProjectTemplate: {
        mt: {id: 1, extra: {intento_provider: 'provider1'}},
      },
    })

    expect(screen.getByTestId('select-intento_provider')).toBeInTheDocument()
  })

  test('selecting a fetched routing saves it under the routing key', async () => {
    getIntentoRouting.mockResolvedValue({a: {name: 'smart_routing'}})

    await renderComponent()

    await waitFor(() => {
      expect(
        screen.getByTestId('option-intento_routing-smart_routing'),
      ).toBeInTheDocument()
    })

    fireEvent.click(
      screen.getByTestId('option-intento_routing-smart_routing'),
    )

    expect(mockSetValue).toHaveBeenCalledWith(
      'intento_routing',
      'smart_routing',
    )
  })

  test('selecting a provider saves it under the provider key', async () => {
    await renderComponent()

    fireEvent.click(screen.getByTestId('option-intento_routing-provider1'))

    expect(mockSetValue).toHaveBeenCalledWith('intento_provider', 'provider1')
  })

  test('pre-translate switch is disabled on cattool page', async () => {
    await renderComponent({isCattoolPage: true})

    expect(screen.getByTestId('switch-enable_mt_analysis')).toBeDisabled()
  })

  test('does not update state after unmounting before the fetch resolves', async () => {
    let resolveFetch
    getIntentoRouting.mockReturnValue(
      new Promise((resolve) => {
        resolveFetch = resolve
      }),
    )

    const {unmount} = await renderComponent()
    unmount()

    expect(() => resolveFetch({a: {name: 'smart_routing'}})).not.toThrow()
  })
})
