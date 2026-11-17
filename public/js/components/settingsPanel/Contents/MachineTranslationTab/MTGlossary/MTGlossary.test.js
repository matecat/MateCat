import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../../../../mocks/mswServer'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {MTGlossary} from './MTGlossary'

jest.mock('./MTGlossaryRow', () => ({
  MTGlossaryRow: ({row}) => (
    <div data-testid={`mt-glossary-row-${row.id}`}>
      {row.name}:{row.isActive ? 'active' : 'inactive'}
    </div>
  ),
}))

const ENGINE_ID = 5

// The endpoint lists the newest key first; the component reverses it.
// Only keys with a glossary are listed: a memory-only key is filtered out.
const keys = [
  {id: 3, name: 'Memory only', has_glossary: false},
  {id: 2, name: 'Marketing', has_glossary: true},
  {id: 1, name: 'Legal', has_glossary: true},
]

const template = (id, mmtGlossaries) => ({
  id,
  mt: {extra: {mmt_glossaries: mmtGlossaries}},
})

const renderGlossary = (currentProjectTemplate) => {
  const setGlossaries = jest.fn()
  const ui = (projectTemplate) => (
    <SettingsPanelContext.Provider
      value={{
        currentProjectTemplate: projectTemplate,
        modifyingCurrentTemplate: jest.fn(),
        projectTemplates: [projectTemplate],
      }}
    >
      <MTGlossary id={ENGINE_ID} setGlossaries={setGlossaries} />
    </SettingsPanelContext.Provider>
  )

  const view = render(ui(currentProjectTemplate))

  return {
    setGlossaries,
    rerenderWith: (projectTemplate) => view.rerender(ui(projectTemplate)),
  }
}

beforeEach(() => {
  global.config = {
    basepath: 'http://localhost/',
    enableMultiDomainApi: false,
    ajaxDomainsNumber: 20,
    ownerIsMe: true,
  }

  mswServer.use(
    http.get(`${config.basepath}api/v3/mmt/:engineId/keys`, () =>
      HttpResponse.json(keys),
    ),
  )
})

afterEach(() => jest.clearAllMocks())

describe('MTGlossary', () => {
  test('marks the glossaries the template lists as active and reports them', async () => {
    const {setGlossaries} = renderGlossary(template(1, [2]))

    expect(await screen.findByTestId('mt-glossary-row-2')).toHaveTextContent(
      'Marketing:active',
    )
    expect(screen.getByTestId('mt-glossary-row-1')).toHaveTextContent(
      'Legal:inactive',
    )
    expect(screen.queryByTestId('mt-glossary-row-3')).not.toBeInTheDocument()
    expect(setGlossaries).toHaveBeenLastCalledWith([2])
  })

  test('marks nothing active when the template has no glossary list', async () => {
    const {setGlossaries} = renderGlossary({id: 1, mt: {extra: {}}})

    expect(await screen.findByTestId('mt-glossary-row-1')).toHaveTextContent(
      'Legal:inactive',
    )
    expect(setGlossaries).toHaveBeenLastCalledWith([])
  })

  test('recomputes the active rows when another template is selected', async () => {
    const {setGlossaries, rerenderWith} = renderGlossary(template(1, [1]))

    await screen.findByTestId('mt-glossary-row-1')
    expect(setGlossaries).toHaveBeenLastCalledWith([1])

    rerenderWith(template(2, [1, 2]))

    await waitFor(() => expect(setGlossaries).toHaveBeenLastCalledWith([1, 2]))
    expect(screen.getByTestId('mt-glossary-row-2')).toHaveTextContent(
      'Marketing:active',
    )
  })

  test('clears the active rows when the selected template has no glossary list', async () => {
    const {setGlossaries, rerenderWith} = renderGlossary(template(1, [1]))

    await screen.findByTestId('mt-glossary-row-1')

    rerenderWith({id: 2, mt: {extra: {}}})

    await waitFor(() => expect(setGlossaries).toHaveBeenLastCalledWith([]))
    expect(screen.getByTestId('mt-glossary-row-1')).toHaveTextContent(
      'Legal:inactive',
    )
  })
})
