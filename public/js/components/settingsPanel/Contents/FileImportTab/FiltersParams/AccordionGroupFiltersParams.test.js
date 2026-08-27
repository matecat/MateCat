import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {AccordionGroupFiltersParams} from './AccordionGroupFiltersParams'
import {FiltersParamsContext} from './FiltersParamsContext'

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

beforeAll(() => {
  window.ResizeObserver = ResizeObserver
})

const baseTemplate = {
  id: 1,
  isTemporary: false,
  xml: {preserve_whitespace: false, translate_elements: [], translate_attributes: []},
  yaml: {translate_keys: [], character_limit: [], context_keys: [], inner_content_type: null},
  json: {
    extract_arrays: true,
    escape_forward_slashes: false,
    translate_keys: [],
    context_keys: [],
    character_limit: [],
    inner_content_type: null,
  },
  msWord: {
    extract_doc_properties: false,
    extract_comments: false,
    extract_headers_footers: true,
    extract_hidden_text: false,
    accept_revisions: false,
    exclude_styles: [],
    exclude_highlight_colors: [],
  },
  msExcel: {
    extract_doc_properties: false,
    extract_hidden_cells: false,
    extract_diagrams: false,
    extract_drawings: false,
    extract_sheet_names: false,
    exclude_columns: [],
  },
  msPowerpoint: {extract_doc_properties: false, extract_notes: true, translate_slides: []},
  dita: {do_not_translate_elements: []},
}

const setup = ({currentTemplateOverrides = {}, templates} = {}) => {
  const currentTemplate = {...baseTemplate, ...currentTemplateOverrides}
  const value = {
    templates: templates ?? [currentTemplate],
    currentTemplate,
    currentProjectTemplateChanged: false,
    modifyingCurrentTemplate: jest.fn(),
  }

  return render(
    <FiltersParamsContext.Provider value={value}>
      <AccordionGroupFiltersParams />
    </FiltersParamsContext.Provider>,
  )
}

describe('AccordionGroupFiltersParams', () => {
  test('renders one accordion section per file format', () => {
    setup()

    expect(screen.getByText('JSON')).toBeInTheDocument()
    expect(screen.getByText('XML')).toBeInTheDocument()
    expect(screen.getByText('YAML')).toBeInTheDocument()
    expect(screen.getByText('MS Word')).toBeInTheDocument()
    expect(screen.getByText('MS Excel')).toBeInTheDocument()
    expect(screen.getByText('MS PowerPoint')).toBeInTheDocument()
    expect(screen.getByText('DITA/DITAMAP')).toBeInTheDocument()
  })

  test('expands the json section content on click', () => {
    setup()

    fireEvent.click(screen.getByText('JSON'))

    expect(screen.getByText('Translate arrays')).toBeInTheDocument()
  })

  test('switches expanded section between two accordions', () => {
    setup()

    fireEvent.click(screen.getByText('JSON'))
    expect(screen.getByText('Translate arrays')).toBeInTheDocument()

    fireEvent.click(screen.getByText('XML'))
    expect(screen.getByText('Preserve whitespaces')).toBeInTheDocument()

    // clicking the same section again collapses it
    fireEvent.click(screen.getByText('XML'))
  })

  test('marks a section as unsaved when it differs from the original template', () => {
    const original = baseTemplate
    const modifiedTemplate = {
      ...baseTemplate,
      json: {...baseTemplate.json, extract_arrays: false},
    }

    setup({
      currentTemplateOverrides: modifiedTemplate,
      templates: [original, modifiedTemplate],
    })

    expect(screen.getByText('●')).toBeInTheDocument()
  })
})
