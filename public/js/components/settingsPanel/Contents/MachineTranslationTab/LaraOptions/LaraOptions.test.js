import React from 'react'
import {act, render, screen, waitFor} from '@testing-library/react'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {LaraOptions, LARA_STYLES, LARA_STYLES_OPTIONS} from './LaraOptions'

// --- Mocks ---

jest.mock('../useOptions', () =>
  jest.fn(() => ({
    watch: jest.fn(() => undefined),
    control: {},
    setValue: jest.fn(),
  })),
)

jest.mock('react-hook-form', () => ({
  Controller: ({render: renderProp, name, control, disabled}) =>
    renderProp({
      field: {
        onChange: jest.fn(),
        value: undefined,
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

jest.mock('../../../../common/Select', () => ({
  Select: ({name, options, activeOption, onSelect, isDisabled, placeholder}) => (
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
  LaraGlossary: ({id}) => <div data-testid="lara-glossary">{id}</div>,
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
    project: {mt_extra: {lara_style_guide_id: null}},
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

afterEach(() => jest.clearAllMocks())

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
    expect(screen.getByTestId('select-lara_style_guide')).toBeInTheDocument()
  })

  test('fetches style guides on mount and populates options', async () => {
    const {laraAuth} = require('../../../../../api/laraAuth')
    const {laraStyleguides} = require('../../../../../api/laraStyleguides/laraStyleguides')

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
      expect(screen.getByTestId('option-lara_style_guide-sg1')).toBeInTheDocument()
    })
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

  test('style guide select shows lara_style_guide_id from CatToolStore when isCattoolPage', async () => {
    const CatToolStore = require('../../../../../stores/CatToolStore')
    CatToolStore.getJobMetadata.mockReturnValue({
      project: {mt_extra: {lara_style_guide_id: 'sg-from-job'}},
    })

    const {laraStyleguides} = require('../../../../../api/laraStyleguides/laraStyleguides')
    laraStyleguides.mockResolvedValue([
      {id: 'sg-from-job', name: 'Job Guide', description: ''},
    ])

    renderComponent({isCattoolPage: true, isAnInternalUser: true})

    await waitFor(() => {
      expect(
        screen.getByTestId('select-lara_style_guide-active'),
      ).toHaveTextContent('sg-from-job')
    })
  })

  test('updates CreateProjectStore when laraStyleGuide watch value changes', async () => {
    const useOptions = require('../useOptions')
    const CreateProjectStore = require('../../../../../stores/CreateProjectStore')

    const mockSetValue = jest.fn()
    useOptions.mockReturnValue({
      watch: jest.fn((field) => (field === 'lara_style_guide' ? 'sg1' : undefined)),
      control: {},
      setValue: mockSetValue,
    })

    renderComponent()

    await waitFor(() => {
      expect(CreateProjectStore.updateProject).toHaveBeenCalledWith({
        laraStyleGuide: 'sg1',
      })
    })
  })

  test('renders LaraGlossary with mt id from template', async () => {
    renderComponent()
    expect(screen.getByTestId('lara-glossary')).toHaveTextContent('1')
  })

  test('handles laraStyleguides fetch failure gracefully', async () => {
    const {laraAuth} = require('../../../../../api/laraAuth')
    const {laraStyleguides} = require('../../../../../api/laraStyleguides/laraStyleguides')

    laraAuth.mockResolvedValue({token: 'abc'})
    laraStyleguides.mockRejectedValue(new Error('network error'))

    // Should not throw
    expect(() => renderComponent({isAnInternalUser: true})).not.toThrow()

    await waitFor(() => {
      expect(laraStyleguides).toHaveBeenCalled()
    })
  })
})
