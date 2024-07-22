import React, {useEffect, useRef} from 'react'
import {act, render, screen, within} from '@testing-library/react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {MachineTranslationTab} from './MachineTranslationTab'
import {mswServer} from '../../../../../../../mocks/mswServer'
import {http, HttpResponse} from 'msw'
import projectTemplatesMock from '../../../../../../../mocks/projectTemplateMock'
import {
  engineListMock,
  mmtKeysMock,
  mtEnginesMock,
} from '../../../../../../../mocks/mtEnginesMock'
import userEvent from '@testing-library/user-event'
import ModalsActions from '../../../../actions/ModalsActions'

beforeEach(() => {
  global.config = {
    basepath: 'http://localhost/',
    enableMultiDomainApi: false,
    ajaxDomainsNumber: 20,
    isLoggedIn: 1,
  }

  mswServer.use(
    http.get(`${config.basepath}api/v3/mmt/:engineId/keys`, () => {
      return HttpResponse.json(mmtKeysMock)
    }),
  )
  mswServer.use(
    http.post(config.basepath, () => {
      return HttpResponse.json()
    }),
  )
})
afterEach(() => jest.clearAllMocks())

const wrapperElement = document.createElement('div')
const WrapperComponent = (contextProps) => {
  const ref = useRef()

  useEffect(() => {
    ref.current.appendChild(wrapperElement)
  }, [])

  return (
    <SettingsPanelContext.Provider
      value={{...contextProps, portalTarget: wrapperElement}}
    >
      <div ref={ref}>
        <MachineTranslationTab />
      </div>
    </SettingsPanelContext.Provider>
  )
}

test('Render Machine translation tab - not logged', async () => {
  global.config.isLoggedIn = false
  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: () => {},
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
  }
  render(
    <SettingsPanelContext.Provider value={values}>
      <MachineTranslationTab />
    </SettingsPanelContext.Provider>,
  )
  const loginButton = screen.getByTestId('login-button')
  expect(loginButton).toBeInTheDocument()
  await userEvent.click(loginButton)
  expect(values.openLoginModal.mock.calls).toHaveLength(1)

  expect(screen.queryByTitle('Add MT engine')).not.toBeInTheDocument()

  const mtName = screen.getByText('MyMemory')
  expect(mtName).toBeInTheDocument()

  const checkboxMtActive = screen.getByTestId('checkbox-mt-active-MyMemory')
  expect(checkboxMtActive).toBeChecked()
})

test('Render Machine translation tab - logged', async () => {
  global.config.isLoggedIn = true
  config.is_cattool = false
  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: () => {},
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
  }
  render(
    <SettingsPanelContext.Provider value={values}>
      <MachineTranslationTab />
    </SettingsPanelContext.Provider>,
  )
  expect(screen.queryByTestId('login-button')).not.toBeInTheDocument()

  expect(screen.getByTitle('Add MT engine')).toBeInTheDocument()

  mtEnginesMock.forEach((en) => {
    const mtName = screen.getByText(en.name)
    expect(mtName).toBeInTheDocument()
  })

  const checkboxMtActive = screen.getAllByTitle('Use in this project')
  expect(checkboxMtActive[0]).toBeChecked()
  expect(checkboxMtActive[1]).not.toBeChecked()
})

test('Add MT', async () => {
  global.config.isLoggedIn = true
  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: () => {},
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
  }
  render(
    <SettingsPanelContext.Provider value={values}>
      <MachineTranslationTab />
    </SettingsPanelContext.Provider>,
  )
  const button = screen.getByTitle('Add MT engine')
  expect(button).toBeInTheDocument()
  await userEvent.click(button)
  const select = screen.getByPlaceholderText('Choose provider')
  expect(select).toBeInTheDocument()
  await userEvent.click(select)
  const container = screen.getByTestId('add-mt-provider')
  engineListMock.forEach((en) => {
    const mtName = within(container).getByText(en.name)
    expect(mtName).toBeInTheDocument()
  })
})

test('In cattool', async () => {
  global.config.isLoggedIn = true
  global.config.ownerIsMe = 1
  config.is_cattool = true
  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: () => {},
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
  }
  render(
    <SettingsPanelContext.Provider value={values}>
      <MachineTranslationTab />
    </SettingsPanelContext.Provider>,
  )
  expect(screen.queryByTestId('Add MT engine')).not.toBeInTheDocument()
  const deleteButtons = screen.queryAllByTestId('delete-mt')
  expect(deleteButtons.length).toBe(0)
})

test('Activate MT', async () => {
  const user = userEvent.setup()
  global.config.isLoggedIn = true

  let values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: jest.fn(),
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
  }
  const {rerender} = render(
    <SettingsPanelContext.Provider value={values}>
      <MachineTranslationTab />
    </SettingsPanelContext.Provider>,
  )
  let activeMTContainert = screen.getByTestId('active-mt')
  let mtName = within(activeMTContainert).getByText('MyMemory')
  expect(mtName).toBeInTheDocument()
  const checkboxMtActive = screen.getByTestId(
    `checkbox-mt-active-${mtEnginesMock[1].name}`,
  )
  await user.click(checkboxMtActive)
  expect(values.modifyingCurrentTemplate.call.length).toBe(1)
  values.currentProjectTemplate.mt.id = mtEnginesMock[1].id
  rerender(
    <SettingsPanelContext.Provider value={values}>
      <MachineTranslationTab />
    </SettingsPanelContext.Provider>,
  )
  activeMTContainert = screen.getByTestId('active-mt')
  mtName = within(activeMTContainert).getByText(mtEnginesMock[1].name)
  expect(mtName).toBeInTheDocument()
})

test('Delete MT Confirm', async () => {
  const user = userEvent.setup()
  global.config.isLoggedIn = true

  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: jest.fn(),
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
  }
  render(
    <SettingsPanelContext.Provider value={values}>
      <MachineTranslationTab />
    </SettingsPanelContext.Provider>,
  )

  const inactiveMTContainert = screen.getByTestId('inactive-mt')
  const deleteButtons =
    within(inactiveMTContainert).queryAllByTestId('delete-mt')
  await user.click(deleteButtons[0])
  const confirmButton = screen.getByText('Confirm')
  expect(confirmButton).toBeInTheDocument()
  await user.click(confirmButton)
  expect(values.modifyingCurrentTemplate.call.length).toBe(1)
})

test('Modern MT and glossary', async () => {
  const user = userEvent.setup()

  global.config.isLoggedIn = true
  config.is_cattool = false

  const projectTemplates = [
    ...projectTemplatesMock.items,
    {
      ...projectTemplatesMock.items[1],
      id: 4,
      mt: {id: 5, extra: {glossaries: [374333]}},
    },
  ]
  const currentProjectTemplate = {
    ...projectTemplates[1],
    mt: {id: 5, extra: {}},
  }

  let modifiedTemplate = currentProjectTemplate
  const modifyingCurrentTemplateFn = (value) =>
    (modifiedTemplate = value(currentProjectTemplate))

  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: modifyingCurrentTemplateFn,
    currentProjectTemplate,
    projectTemplates,
  }
  render(<WrapperComponent {...values} />)
  let activeMTContainert = screen.getByTestId('active-mt')
  let mtName = within(activeMTContainert).getByText('ModernMT')
  expect(mtName).toBeInTheDocument()
  const glossaryButton = await screen.findByTitle('Glossary options')
  expect(glossaryButton).toBeInTheDocument()

  await user.click(glossaryButton)

  const rowGlossary1 = screen.getByTestId('mtglossary-active-374333')
  const rowGlossary2 = screen.getByTestId('mtglossary-active-374533')
  expect(rowGlossary1).toBeInTheDocument()
  expect(rowGlossary2).toBeInTheDocument()

  await user.click(rowGlossary1)

  expect(modifiedTemplate).toEqual({
    ...currentProjectTemplate,
    mt: {id: 5, extra: {glossaries: [374333]}},
  })

  const spyShowModal = jest.spyOn(ModalsActions, 'showModalComponent')

  await user.click(screen.getByTestId('delete-mtglossary-374333'))

  const modalContent = spyShowModal.mock.calls[0][1].content
  expect(modalContent).toBe(
    'The glossary you are about to delete is linked to an MT license and used in the following project creation template(s):',
  )

  const newButton = screen.getByText('New')
  expect(newButton).toBeInTheDocument()

  await user.click(newButton)

  const inputName = screen.getByTestId('mtglossary-create-name')
  expect(inputName).toBeInTheDocument()

  const confirm = screen.getByTestId('mtglossary-create-confirm')
  expect(confirm).toBeDisabled()
})

test('DeepL and glossary', async () => {
  mswServer.use(
    http.get(`${config.basepath}api/v3/deepl/:engineId/glossaries`, () => {
      return HttpResponse.json({
        glossaries: [
          {
            glossary_id: '316e350e-81d1-4781-900c-2ab69aa4e6f4',
            name: 'Test',
            ready: true,
            source_lang: 'it',
            target_lang: 'en',
            creation_time: '2024-02-14T16:39:14.56105Z',
            entry_count: 2,
          },
          {
            glossary_id: '316e350e-81d1-4781-900c-3abc',
            name: 'Test2',
            ready: true,
            source_lang: 'it',
            target_lang: 'en',
            creation_time: '2024-02-14T16:39:14.56105Z',
            entry_count: 2,
          },
        ],
      })
    }),
  )

  const user = userEvent.setup()

  global.config.isLoggedIn = true
  config.is_cattool = false

  const projectTemplates = [
    ...projectTemplatesMock.items,
    {
      ...projectTemplatesMock.items[1],
      id: 4,
      mt: {
        id: 7,
        extra: {
          deepl_formality: 'default',
          deepl_id_glossary: '316e350e-81d1-4781-900c-2ab69aa4e6f4',
        },
      },
    },
  ]
  const currentProjectTemplate = {
    ...projectTemplates[1],
    mt: {id: 7, extra: {deepl_formality: 'default'}},
  }

  let modifiedTemplate = currentProjectTemplate
  const modifyingCurrentTemplateFn = (value) =>
    (modifiedTemplate = value(currentProjectTemplate))

  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: modifyingCurrentTemplateFn,
    currentProjectTemplate,
    projectTemplates,
  }
  render(<WrapperComponent {...values} />)
  let activeMTContainert = screen.getByTestId('active-mt')
  let mtName = within(activeMTContainert).getByText(
    'DeepL - Accurate translations for individuals and Teams.',
  )
  expect(mtName).toBeInTheDocument()
  const glossaryButton = screen.getByTitle('Glossary options')
  expect(glossaryButton).toBeInTheDocument()

  await user.click(glossaryButton)

  const rowGlossary1 = screen.getByTestId(
    'deeplglossary-active-316e350e-81d1-4781-900c-2ab69aa4e6f4',
  )
  const rowGlossary2 = screen.getByTestId(
    'deeplglossary-active-316e350e-81d1-4781-900c-3abc',
  )
  expect(rowGlossary1).toBeInTheDocument()
  expect(rowGlossary2).toBeInTheDocument()

  await user.click(rowGlossary2)
  expect(rowGlossary2).toBeChecked()

  await user.click(rowGlossary1)
  expect(rowGlossary1).toBeChecked()
  expect(rowGlossary2).not.toBeChecked()

  expect(modifiedTemplate).toEqual({
    ...currentProjectTemplate,
    mt: {
      id: 7,
      extra: {
        deepl_formality: 'default',
        deepl_id_glossary: '316e350e-81d1-4781-900c-2ab69aa4e6f4',
      },
    },
  })

  const spyShowModal = jest.spyOn(ModalsActions, 'showModalComponent')

  await user.click(
    screen.getByTestId(
      'delete-deeplglossary-316e350e-81d1-4781-900c-2ab69aa4e6f4',
    ),
  )

  const modalContent = spyShowModal.mock.calls[0][1].content
  expect(modalContent).toBe(
    'The glossary you are about to delete is linked to a DeepL license and used in the following project creation template(s):',
  )

  const newButton = screen.getByText('New')
  expect(newButton).toBeInTheDocument()

  await user.click(newButton)

  const inputName = screen.getByTestId('deeplglossary-create-name')
  expect(inputName).toBeInTheDocument()

  const confirm = screen.getByTestId('deeplglossary-create-confirm')
  expect(confirm).toBeDisabled()

  const formalitySelect = screen.getByText('Default')
  expect(formalitySelect).toBeInTheDocument()

  await user.click(formalitySelect)

  expect(screen.getByText('Informal')).toBeInTheDocument()
  expect(screen.getByText('Formal')).toBeInTheDocument()

  await user.click(screen.getByText('Formal'))

  expect(modifiedTemplate).toEqual({
    ...currentProjectTemplate,
    mt: {
      id: 7,
      extra: {
        deepl_formality: 'prefer_more',
      },
    },
  })
})
