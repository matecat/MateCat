import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../../../../mocks/mswServer'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {LaraGlossary} from './LaraGlossary'

jest.mock('./LaraGlossaryRow', () => ({
  LaraGlossaryRow: ({row}) => (
    <div data-testid={`lara-glossary-row-${row.id}`}>
      {row.name}:{row.isActive ? 'active' : 'inactive'}
    </div>
  ),
}))

const ENGINE_ID = 7

const glossaries = [
  {id: 'g1', name: 'Legal'},
  {id: 'g2', name: 'Marketing'},
]

const template = (id, laraGlossaries) => ({
  id,
  mt: {extra: {lara_glossaries: laraGlossaries}},
})

const mockGlossariesEndpoint = (
  respond = () => HttpResponse.json(glossaries),
) => {
  const requests = []

  mswServer.use(
    http.get(
      `${config.basepath}api/v3/lara/:engineId/glossaries`,
      ({params}) => {
        requests.push(params.engineId)
        return respond()
      },
    ),
  )

  return requests
}

const renderGlossary = ({
  currentProjectTemplate,
  setGlossaries = jest.fn(),
  isCattoolPage = false,
}) => {
  const ui = (projectTemplate) => (
    <SettingsPanelContext.Provider
      value={{
        currentProjectTemplate: projectTemplate,
        modifyingCurrentTemplate: jest.fn(),
      }}
    >
      <LaraGlossary
        id={ENGINE_ID}
        setGlossaries={setGlossaries}
        isCattoolPage={isCattoolPage}
      />
    </SettingsPanelContext.Provider>
  )

  const view = render(ui(currentProjectTemplate))

  return {
    ...view,
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
})

afterEach(() => jest.clearAllMocks())

describe('LaraGlossary', () => {
  test('marks the glossaries the template lists as active and reports them', async () => {
    const requests = mockGlossariesEndpoint()

    const {setGlossaries} = renderGlossary({
      currentProjectTemplate: template(1, ['g2']),
    })

    expect(await screen.findByTestId('lara-glossary-row-g2')).toHaveTextContent(
      'Marketing:active',
    )
    expect(screen.getByTestId('lara-glossary-row-g1')).toHaveTextContent(
      'Legal:inactive',
    )
    expect(requests).toEqual([String(ENGINE_ID)])
    expect(setGlossaries).toHaveBeenLastCalledWith(['g2'])
  })

  test('marks nothing active when the template has no glossary list', async () => {
    mockGlossariesEndpoint()

    const {setGlossaries} = renderGlossary({
      currentProjectTemplate: {id: 1, mt: {extra: {}}},
    })

    expect(await screen.findByTestId('lara-glossary-row-g1')).toHaveTextContent(
      'Legal:inactive',
    )
    expect(setGlossaries).toHaveBeenLastCalledWith([])
  })

  test('recomputes the active rows when another template is selected', async () => {
    mockGlossariesEndpoint()

    const {setGlossaries, rerenderWith} = renderGlossary({
      currentProjectTemplate: template(1, ['g1']),
    })

    await screen.findByTestId('lara-glossary-row-g1')
    expect(setGlossaries).toHaveBeenLastCalledWith(['g1'])

    rerenderWith(template(2, ['g1', 'g2']))

    await waitFor(() =>
      expect(setGlossaries).toHaveBeenLastCalledWith(['g1', 'g2']),
    )
    expect(screen.getByTestId('lara-glossary-row-g2')).toHaveTextContent(
      'Marketing:active',
    )
  })

  test('does not load the glossaries for a user who does not own the job', async () => {
    global.config.ownerIsMe = false
    const requests = mockGlossariesEndpoint()

    const {setGlossaries} = renderGlossary({
      currentProjectTemplate: template(1, ['g1']),
      isCattoolPage: true,
    })

    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(requests).toEqual([])
    expect(setGlossaries).not.toHaveBeenCalled()
    expect(screen.queryByText('Glossaries')).not.toBeInTheDocument()
  })

  test('offers to create a glossary when the account has none', async () => {
    mockGlossariesEndpoint(() => HttpResponse.json([]))

    renderGlossary({currentProjectTemplate: template(1, [])})

    expect(
      await screen.findByText('Create a glossary on Lara'),
    ).toBeInTheDocument()
  })

  test('hides the management buttons on the cattool page', async () => {
    mockGlossariesEndpoint()

    renderGlossary({
      currentProjectTemplate: template(1, ['g1']),
      isCattoolPage: true,
    })

    await screen.findByTestId('lara-glossary-row-g1')
    expect(screen.getByText('Glossaries')).toBeInTheDocument()
    expect(
      screen.queryByText('Manage your Lara glossaries'),
    ).not.toBeInTheDocument()
  })
})
