import React, {useRef, useState} from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import TermForm from './TermForm'
import {TabGlossaryContext} from './TabGlossaryContext'

jest.mock('../../../actions/SegmentActions', () => ({
  addGlossaryItem: jest.fn(),
  updateGlossaryItem: jest.fn(),
}))

jest.mock('../../../actions/CatToolActions', () => ({
  setDomains: jest.fn(),
}))

const SegmentActions = require('../../../actions/SegmentActions')
const CatToolActions = require('../../../actions/CatToolActions')

const emptyTermForm = {
  definition: '',
  originalTerm: '',
  originalDescription: '',
  originalExample: '',
  translatedTerm: '',
  translatedDescription: '',
  translatedExample: '',
}

const segment = {sid: '1'}

const Harness = ({
  initialTermForm = emptyTermForm,
  initialSelectsActive = {keys: [], domain: undefined, subdomain: undefined},
  modifyElement,
  isLoading = false,
  domainsResponse,
  getRequestPayloadTemplate = jest.fn(() => ({payload: true})),
  resetForm = jest.fn(),
} = {}) => {
  const [termForm, setTermForm] = useState(initialTermForm)
  const [selectsActive, setSelectsActive] = useState(initialSelectsActive)
  const [showMore, setShowMore] = useState(false)
  const ref = useRef()
  const setIsLoading = jest.fn()

  return (
    <div ref={ref}>
      <TabGlossaryContext.Provider
        value={{
          isLoading,
          termForm,
          setTermForm,
          selectsActive,
          setSelectsActive,
          modifyElement,
          showMore,
          setShowMore,
          resetForm,
          domainsResponse,
          getRequestPayloadTemplate,
          setIsLoading,
          segment,
          ref,
          keys: [],
          domains: [],
          subdomains: [],
          setDomains: jest.fn(),
          setSubdomains: jest.fn(),
        }}
      >
        <TermForm />
      </TabGlossaryContext.Provider>
    </div>
  )
}

beforeAll(() => {
  global.config = Object.assign(global.config ?? {}, {
    isSourceRTL: false,
    isTargetRTL: false,
  })
})

afterEach(() => {
  jest.clearAllMocks()
})

describe('TermForm', () => {
  test('renders the mandatory fields', () => {
    render(<Harness />)
    expect(screen.getByText('Original term*')).toBeInTheDocument()
    expect(screen.getByText('Translated term*')).toBeInTheDocument()
  })

  test('typing into the original term field updates the value', () => {
    render(<Harness />)
    fireEvent.change(
      document.querySelector('input[name="glossary-term-original"]'),
      {target: {value: 'gatto'}},
    )
    expect(
      document.querySelector('input[name="glossary-term-original"]'),
    ).toHaveValue('gatto')
  })

  test('submitting with empty mandatory fields highlights them and does not dispatch', () => {
    render(<Harness />)
    fireEvent.click(screen.getByRole('button', {name: 'Add'}))

    expect(
      document.querySelector('.glossary-term-original.highlight_mandatory'),
    ).toBeInTheDocument()
    expect(SegmentActions.addGlossaryItem).not.toHaveBeenCalled()
  })

  test('submitting a valid new term calls addGlossaryItem', () => {
    render(
      <Harness
        initialTermForm={{
          ...emptyTermForm,
          originalTerm: 'gatto',
          translatedTerm: 'cat',
        }}
        initialSelectsActive={{
          keys: [{key: 'key-1', name: 'Key One'}],
          domain: undefined,
          subdomain: undefined,
        }}
      />,
    )
    fireEvent.click(screen.getByRole('button', {name: 'Add'}))

    expect(SegmentActions.addGlossaryItem).toHaveBeenCalledWith({
      payload: true,
    })
    expect(CatToolActions.setDomains).toHaveBeenCalled()
  })

  test('submitting a valid update calls updateGlossaryItem', () => {
    render(
      <Harness
        initialTermForm={{
          ...emptyTermForm,
          originalTerm: 'gatto',
          translatedTerm: 'cat',
        }}
        initialSelectsActive={{
          keys: [{key: 'key-1', name: 'Key One'}],
          domain: undefined,
          subdomain: undefined,
        }}
        modifyElement={{term_id: '1'}}
      />,
    )
    fireEvent.click(screen.getByRole('button', {name: 'Update'}))

    expect(SegmentActions.updateGlossaryItem).toHaveBeenCalledWith({
      payload: true,
    })
    expect(SegmentActions.addGlossaryItem).not.toHaveBeenCalled()
  })

  test('Cancel button calls resetForm', () => {
    const resetForm = jest.fn()
    render(<Harness resetForm={resetForm} />)
    fireEvent.click(screen.getByRole('button', {name: 'Cancel'}))
    expect(resetForm).toHaveBeenCalled()
  })

  test('toggling "More options" reveals the extra fields', () => {
    render(<Harness />)
    expect(screen.getByText('More options')).toBeInTheDocument()
    fireEvent.click(screen.getByText('More options'))
    expect(screen.getByText('Hide options')).toBeInTheDocument()
    expect(screen.getAllByText('Notes').length).toBeGreaterThan(0)
  })

  test('pressing Escape resets the form', () => {
    const resetForm = jest.fn()
    render(<Harness resetForm={resetForm} />)
    fireEvent.keyDown(
      document.querySelector('input[name="glossary-term-original"]'),
      {key: 'Escape'},
    )
    expect(resetForm).toHaveBeenCalled()
  })

  test('pressing Ctrl+Enter submits when not loading', () => {
    render(
      <Harness
        initialTermForm={{
          ...emptyTermForm,
          originalTerm: 'gatto',
          translatedTerm: 'cat',
        }}
        initialSelectsActive={{
          keys: [{key: 'key-1', name: 'Key One'}],
          domain: undefined,
          subdomain: undefined,
        }}
      />,
    )
    fireEvent.keyDown(
      document.querySelector('input[name="glossary-term-original"]'),
      {key: 'Enter', ctrlKey: true},
    )
    expect(SegmentActions.addGlossaryItem).toHaveBeenCalled()
  })

  test('pressing Ctrl+Enter does nothing while loading', () => {
    render(
      <Harness
        isLoading
        initialTermForm={{
          ...emptyTermForm,
          originalTerm: 'gatto',
          translatedTerm: 'cat',
        }}
        initialSelectsActive={{
          keys: [{key: 'key-1', name: 'Key One'}],
          domain: undefined,
          subdomain: undefined,
        }}
      />,
    )
    fireEvent.keyDown(
      document.querySelector('input[name="glossary-term-original"]'),
      {key: 'Enter', ctrlKey: true},
    )
    expect(SegmentActions.addGlossaryItem).not.toHaveBeenCalled()
  })
})
