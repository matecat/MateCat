import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {LaraOptions, LARA_STYLES, LARA_STYLES_OPTIONS} from './LaraOptions'
import useOptions from '../useOptions'

const mockControllerOnChange = {}
let mockSetValue

// --- Mocks ---

jest.mock('../useOptions', () =>
  jest.fn(() => ({
    control: {},
    setValue: jest.fn(),
  })),
)

jest.mock('react-hook-form', () => ({
  Controller: ({render: renderProp, name, control, disabled}) => {
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

jest.mock('../../../../common/Select', () => ({
  Select: ({
    name,
    options,
    activeOption,
    onSelect,
    isDisabled,
    placeholder,
  }) => (
    <div data-testid={`select-${name}`}>
      <span data-testid={`select-${name}-active`}>
        {activeOption?.id ?? placeholder ?? ''}
      </span>
      {(options ?? []).map((opt) => (
        <button
          key={opt.id}
          data-testid={`option-${name}-${opt.id}`}
          disabled={isDisabled}
          onClick={() => onSelect(opt)}
        >
          {opt.id}
        </button>
      ))}
    </div>
  ),
}))

jest.mock('../LaraGlossary/LaraGlossary', () => ({
  LaraGlossary: ({id, setGlossaries}) => (
    <div data-testid="lara-glossary">
      <span>{id}</span>
      <button data-testid="set-glossaries" onClick={() => setGlossaries(['gl-1'])}>
        set glossaries
      </button>
    </div>
  ),
}))

jest.mock('../../../../../api/laraAuth', () => ({
  laraAuth: jest.fn(() => Promise.resolve({token: 'test-token'})),
}))

jest.mock('../../../../../api/laraStyleguides/laraStyleguides', () => ({
  laraStyleguides: jest.fn(() => Promise.resolve([])),
}))

jest.mock('../../../../../stores/CreateProjectStore', () => ({
  updateProject: jest.fn(),
}))

jest.mock('../../../../../stores/CatToolStore', () => ({
  getJobMetadata: jest.fn(() => ({
    project: {mt_extra: {lara_style_guideline_id: null}},
  })),
}))

// --- Helpers ---

const defaultTemplate = {
  mt: {
    id: 1,
    extra: {
      lara_style: LARA_STYLES.FAITHFUL,
      enable_mt_analysis: false,
    },
  },
}

const renderComponent = ({
  isCattoolPage = false,
  currentProjectTemplate = defaultTemplate,
  isAnInternalUser = false,
} = {}) => {
  global.config = Object.assign(global.config ?? {}, {
    isAnInternalUser,
  })

  return render(
    <SettingsPanelContext.Provider
      value={{
        currentProjectTemplate,
        modifyingCurrentTemplate: jest.fn(),
      }}
    >
      <LaraOptions isCattoolPage={isCattoolPage} />
    </SettingsPanelContext.Provider>,
  )
}

beforeEach(() => {
  jest.clearAllMocks()
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

describe('LaraOptions', () => {
  test('renders Pre-translate and Style sections', async () => {
    renderComponent()

    expect(screen.getByText('Pre-translate files')).toBeInTheDocument()
    expect(screen.getByText('Style')).toBeInTheDocument()
    expect(screen.getByText('Glossaries')).toBeInTheDocument()
    expect(screen.getByTestId('lara-glossary')).toBeInTheDocument()
  })

  test('does not render Style guide section for non-internal users', async () => {
    renderComponent({isAnInternalUser: false})

    expect(screen.queryByText('Style guide')).not.toBeInTheDocument()
  })

  test('renders Style guide section for internal users', async () => {
    renderComponent({isAnInternalUser: true})

    expect(screen.getByText('Style guide')).toBeInTheDocument()
    expect(
      screen.getByTestId('select-lara_style_guideline_id'),
    ).toBeInTheDocument()
  })

  test('fetches style guides on mount and populates options', async () => {
    const {laraAuth} = require('../../../../../api/laraAuth')
    const {
      laraStyleguides,
    } = require('../../../../../api/laraStyleguides/laraStyleguides')

    laraAuth.mockResolvedValue({token: 'abc'})
    laraStyleguides.mockResolvedValue([
      {id: 'sg1', name: 'Guide 1', description: 'Desc 1'},
    ])

    renderComponent({isAnInternalUser: true})

    await waitFor(() => {
      expect(laraAuth).toHaveBeenCalled()
      expect(laraStyleguides).toHaveBeenCalledWith({token: 'abc'})
    })

    await waitFor(() => {
      expect(
        screen.getByTestId('option-lara_style_guideline_id-sg1'),
      ).toBeInTheDocument()
    })
  })

  test('sets default lara_style when project template extra has no style', () => {
    renderComponent({
      currentProjectTemplate: {
        mt: {
          id: 1,
          extra: {
            enable_mt_analysis: false,
          },
        },
      },
    })

    expect(mockSetValue).toHaveBeenCalledWith('lara_style', LARA_STYLES.FAITHFUL)
  })

  test('forwards style selection through controller onChange', () => {
    renderComponent()

    fireEvent.click(screen.getByTestId('option-lara_style-fluid'))

    expect(mockControllerOnChange.lara_style).toHaveBeenCalledWith('fluid')
  })

  test('forwards style guide selection through controller onChange', async () => {
    const {laraAuth} = require('../../../../../api/laraAuth')
    const {
      laraStyleguides,
    } = require('../../../../../api/laraStyleguides/laraStyleguides')

    laraAuth.mockResolvedValue({token: 'abc'})
    laraStyleguides.mockResolvedValue([
      {id: 'sg1', name: 'Guide 1', description: 'Desc 1'},
    ])

    renderComponent({isAnInternalUser: true})

    await waitFor(() => {
      expect(
        screen.getByTestId('option-lara_style_guideline_id-sg1'),
      ).toBeInTheDocument()
    })

    fireEvent.click(screen.getByTestId('option-lara_style_guideline_id-sg1'))

    expect(mockControllerOnChange.lara_style_guideline_id).toHaveBeenCalledWith(
      'sg1',
    )
  })

  test('passes glossary changes to useOptions setValue integration', () => {
    renderComponent()

    fireEvent.click(screen.getByTestId('set-glossaries'))

    expect(mockSetValue).toHaveBeenCalledWith('lara_glossaries', ['gl-1'])
  })

  test('does not call Lara auth on mount for non-internal users', () => {
    const {laraAuth} = require('../../../../../api/laraAuth')

    renderComponent({isAnInternalUser: false})

    expect(laraAuth).not.toHaveBeenCalled()
  })

  test('renders all three style options in the Style select', async () => {
    renderComponent()

    LARA_STYLES_OPTIONS.forEach(({id}) => {
      expect(screen.getByTestId(`option-lara_style-${id}`)).toBeInTheDocument()
    })
  })

  test('pre-translate switch is disabled on cattool page', async () => {
    renderComponent({isCattoolPage: true})

    const switchEl = screen.getByTestId('switch-enable_mt_analysis')
    expect(switchEl).toBeDisabled()
  })

  test('renders LaraGlossary with mt id from template', async () => {
    renderComponent()
    expect(screen.getByTestId('lara-glossary')).toHaveTextContent('1')
  })

  test('handles laraStyleguides fetch failure gracefully', async () => {
    const {laraAuth} = require('../../../../../api/laraAuth')
    const {
      laraStyleguides,
    } = require('../../../../../api/laraStyleguides/laraStyleguides')

    laraAuth.mockResolvedValue({token: 'abc'})
    laraStyleguides.mockRejectedValue(new Error('network error'))

    // Should not throw
    expect(() => renderComponent({isAnInternalUser: true})).not.toThrow()

    await waitFor(() => {
      expect(laraStyleguides).toHaveBeenCalled()
    })
  })
})
