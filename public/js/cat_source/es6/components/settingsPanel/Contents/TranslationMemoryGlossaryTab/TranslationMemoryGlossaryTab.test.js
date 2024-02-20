import React, {useEffect, useRef} from 'react'
import {act, render, screen} from '@testing-library/react'
import projectTemplatesMock from '../../../../../../../mocks/projectTemplateMock'
import tmKeysMock from '../../../../../../../mocks/tmKeysMock'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {
  SPECIAL_ROWS_ID,
  TranslationMemoryGlossaryTab,
} from './TranslationMemoryGlossaryTab'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import userEvent from '@testing-library/user-event'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
  isLoggedIn: 1,
  ownerIsMe: true,
}

const contextMockValues = () => {
  let _tmKeys = tmKeysMock.tm_keys.map((key) => ({
    ...key,
    id: key.key,
    r: false,
    w: false,
    isActive: false,
    isLocked: !key.owner,
  }))
  const setTmKeys = (value) =>
    (_tmKeys = typeof value === 'function' ? value(_tmKeys) : value)

  const projectTemplatesMockProxy = projectTemplatesMock.items.map(
    (template) =>
      new Proxy(template, {get: (target, prop) => target[SCHEMA_KEYS[prop]]}),
  )

  const props = {
    modifyingCurrentTemplate: () => {},
    currentProjectTemplate: projectTemplatesMockProxy[0],
    projectTemplates: projectTemplatesMockProxy,
    setTmKeys,
  }

  Object.defineProperty(props, 'tmKeys', {
    get: () => _tmKeys,
    enumerable: true,
  })

  return props
}

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
        <TranslationMemoryGlossaryTab />
      </div>
    </SettingsPanelContext.Provider>
  )
}

const ROW_COLUMNS_TESTID = {
  NAME: 'tmkey-row-name',
  LOOKUP: 'tmkey-lookup',
  UPDATE: 'tmkey-update',
}
const getRowElementById = ({id, column}) =>
  screen.queryByTestId(`${column}-${id}`)

test('Operation about MyMemory row', async () => {
  const user = userEvent.setup()
  const contextValues = contextMockValues()

  render(<WrapperComponent {...contextValues} />)

  const rowName = getRowElementById({
    column: ROW_COLUMNS_TESTID.NAME,
    id: SPECIAL_ROWS_ID.defaultTranslationMemory,
  })
  expect(rowName).toBeInTheDocument()

  const rowLookup = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: SPECIAL_ROWS_ID.defaultTranslationMemory,
  })
  expect(rowLookup).toBeEnabled()
  expect(rowLookup).toBeChecked()

  const rowUpdate = getRowElementById({
    column: ROW_COLUMNS_TESTID.UPDATE,
    id: SPECIAL_ROWS_ID.defaultTranslationMemory,
  })
  expect(rowUpdate).toBeDisabled()
  expect(rowUpdate).toBeChecked()

  await act(async () => user.click(rowLookup))
  expect(rowLookup).not.toBeChecked()
})

test('Enabled/disable key', async () => {
  const user = userEvent.setup()
  const contextValues = contextMockValues()

  const {rerender} = render(<WrapperComponent {...contextValues} />)

  let rowLookup = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: 'e32699c0a360e08948fe',
  })
  expect(rowLookup).toBeEnabled()
  expect(rowLookup).not.toBeChecked()

  let rowUpdate = getRowElementById({
    column: ROW_COLUMNS_TESTID.UPDATE,
    id: 'e32699c0a360e08948fe',
  })
  expect(rowUpdate).not.toBeInTheDocument()

  // activate key
  await act(async () => user.click(rowLookup))

  rerender(<WrapperComponent {...contextValues} />)

  rowLookup = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: 'e32699c0a360e08948fe',
  })
  rowUpdate = getRowElementById({
    column: ROW_COLUMNS_TESTID.UPDATE,
    id: 'e32699c0a360e08948fe',
  })
  expect(rowLookup).toBeChecked()
  expect(rowUpdate).toBeChecked()

  // disable write and read
  await act(async () => user.click(rowLookup))

  rerender(<WrapperComponent {...contextValues} />)

  rowLookup = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: 'e32699c0a360e08948fe',
  })
  rowUpdate = getRowElementById({
    column: ROW_COLUMNS_TESTID.UPDATE,
    id: 'e32699c0a360e08948fe',
  })

  expect(rowLookup).not.toBeChecked()
  expect(rowUpdate).toBeChecked()

  await act(async () => user.click(rowUpdate))

  rerender(<WrapperComponent {...contextValues} />)

  rowUpdate = getRowElementById({
    column: ROW_COLUMNS_TESTID.UPDATE,
    id: 'e32699c0a360e08948fe',
  })

  expect(
    (rowUpdate = getRowElementById({
      column: ROW_COLUMNS_TESTID.UPDATE,
      id: 'e32699c0a360e08948fe',
    })),
  ).not.toBeInTheDocument()
})

test('Create new resource', async () => {
  const user = userEvent.setup()
  const contextValues = contextMockValues()

  render(<WrapperComponent {...contextValues} />)

  const button = screen.getByText('New resource')
  expect(button).toBeInTheDocument()

  await act(async () => user.click(button))

  const rowName = screen.getByTestId(SPECIAL_ROWS_ID.newResource)

  expect(rowName).toBeInTheDocument()
})
