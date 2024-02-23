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
import {TranslationMemoryGlossaryTab} from '../TranslationMemoryGlossaryTab'

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

test('Modern MT', async () => {
  const user = userEvent.setup()
  global.config.isLoggedIn = true
  config.is_cattool = false
  const projectTemplate = {...projectTemplatesMock[0], mt: {id: 5}}
  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: jest.fn(),
    currentProjectTemplate: projectTemplate,
    projectTemplates: projectTemplatesMock.items,
  }
  render(<WrapperComponent {...values} />)
  let activeMTContainert = screen.getByTestId('active-mt')
  let mtName = within(activeMTContainert).getByText('ModernMT')
  expect(mtName).toBeInTheDocument()
  const glossaryButton = screen.getByTitle('Glossary options')
  expect(glossaryButton).toBeInTheDocument()

  await user.click(glossaryButton)
  const addGlossary = screen.getByText('New glossary') // l'empty state non lo trova perchè ci sono dei glossari ritornati in mock dall'api - api/v3/mmt/:engineId/keysgi
  expect(addGlossary).toBeInTheDocument()
})
