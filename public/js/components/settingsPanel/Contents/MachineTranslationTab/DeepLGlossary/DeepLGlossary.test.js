import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../../../../mocks/mswServer'
import {SettingsPanelContext} from '../../../SettingsPanelContext'
import {DeepLGlossary} from './DeepLGlossary'

jest.mock('./DeepLGlossaryRow', () => ({
  DeepLGlossaryRow: ({row}) => (
    <div data-testid={`deepl-glossary-row-${row.id}`}>
      {row.name}:{row.isActive ? 'active' : 'inactive'}
    </div>
  ),
}))

jest.mock('./DeepLGlossaryNoneRow', () => ({
  DeepLGlossaryNoneRow: ({row}) => (
    <div data-testid="deepl-glossary-row-none">
      {row.name}:{row.isActive ? 'active' : 'inactive'}
    </div>
  ),
}))

const ENGINE_ID = 3

// The endpoint lists the newest glossary first; the component reverses it.
const glossaries = [
  {glossary_id: 'gB', name: 'Marketing'},
  {glossary_id: 'gA', name: 'Legal'},
]

const template = (id, deeplIdGlossary) => ({
  id,
  mt: {extra: {deepl_id_glossary: deeplIdGlossary}},
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
      <DeepLGlossary id={ENGINE_ID} setGlossaries={setGlossaries} />
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
    http.get(`${config.basepath}api/v3/deepl/:engineId/glossaries`, () =>
      HttpResponse.json({glossaries}),
    ),
  )
})

afterEach(() => jest.clearAllMocks())

describe('DeepLGlossary', () => {
  test('marks the glossary the template selects as active and reports it', async () => {
    const {setGlossaries} = renderGlossary(template(1, 'gA'))

    expect(
      await screen.findByTestId('deepl-glossary-row-gA'),
    ).toHaveTextContent('Legal:active')
    expect(screen.getByTestId('deepl-glossary-row-gB')).toHaveTextContent(
      'Marketing:inactive',
    )
    expect(screen.getByTestId('deepl-glossary-row-none')).toHaveTextContent(
      'None:inactive',
    )
    expect(setGlossaries).toHaveBeenLastCalledWith('gA')
  })

  test('selects the none row when the template has no glossary', async () => {
    const {setGlossaries} = renderGlossary(template(1, undefined))

    expect(
      await screen.findByTestId('deepl-glossary-row-none'),
    ).toHaveTextContent('None:active')
    expect(setGlossaries).toHaveBeenLastCalledWith('')
  })

  test('recomputes the active row when another template is selected', async () => {
    const {setGlossaries, rerenderWith} = renderGlossary(template(1, 'gA'))

    await screen.findByTestId('deepl-glossary-row-gA')
    expect(setGlossaries).toHaveBeenLastCalledWith('gA')

    rerenderWith(template(2, 'gB'))

    await waitFor(() => expect(setGlossaries).toHaveBeenLastCalledWith('gB'))
    expect(screen.getByTestId('deepl-glossary-row-gB')).toHaveTextContent(
      'Marketing:active',
    )
    expect(screen.getByTestId('deepl-glossary-row-gA')).toHaveTextContent(
      'Legal:inactive',
    )
  })

  test('falls back to the none row when the selected template has no glossary', async () => {
    const {setGlossaries, rerenderWith} = renderGlossary(template(1, 'gA'))

    await screen.findByTestId('deepl-glossary-row-gA')

    rerenderWith({id: 2, mt: {extra: {}}})

    await waitFor(() => expect(setGlossaries).toHaveBeenLastCalledWith(''))
    expect(screen.getByTestId('deepl-glossary-row-none')).toHaveTextContent(
      'None:active',
    )
  })
})
