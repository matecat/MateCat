import React, {useEffect, useRef} from 'react'
import {act, render, screen, waitFor} from '@testing-library/react'
import projectTemplatesMock from '../../../../../../../mocks/projectTemplateMock'
import tmKeysMock from '../../../../../../../mocks/tmKeysMock'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {
  SPECIAL_ROWS_ID,
  TranslationMemoryGlossaryTab,
} from './TranslationMemoryGlossaryTab'
import {SCHEMA_KEYS} from '../../../../hooks/useProjectTemplates'
import userEvent from '@testing-library/user-event'
import {mswServer} from '../../../../../../../mocks/mswServer'
import {HttpResponse, http} from 'msw'
import ModalsActions from '../../../../actions/ModalsActions'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
  isLoggedIn: 1,
  ownerIsMe: true,
  defaults: {
    tag_projection: 1,
  },
}

const contextMockValues = ({
  noTmKeys = false,
  tmKeysMockArray = tmKeysMock.tm_keys,
} = {}) => {
  let _tmKeys = !noTmKeys
    ? tmKeysMockArray.map((key) => ({
        ...key,
        id: key.key,
        r: false,
        w: false,
        isActive: false,
        isLocked: !key.owner,
      }))
    : []
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

test('Create and delete new resource', async () => {
  mswServer.use(
    http.post(config.basepath, ({request}) => {
      const url = new URL(request.url)
      const action = url.searchParams.get('action')
      const response =
        action === 'createRandUser'
          ? {
              errors: [],
              data: {
                key: '6f03df0307c7a161afa9',
                id: 'MyMemory_5007d86025f58b50f29c',
                pass: 'f22815f87d',
                mtLangSupported: true,
                error: {
                  code: 0,
                  message: '',
                },
              },
            }
          : {errors: [], data: []}

      return HttpResponse.json(response)
    }),
  )

  const user = userEvent.setup()
  const contextValues = contextMockValues({noTmKeys: true})

  const {rerender} = render(<WrapperComponent {...contextValues} />)

  const button = screen.getByTestId('new-resource-tm')
  expect(button).toBeInTheDocument()

  await act(async () => user.click(button))

  const rowName = screen.getByTestId(SPECIAL_ROWS_ID.newResource)

  expect(rowName).toBeInTheDocument()
  expect(rowName).toHaveFocus()

  const buttonConfirm = screen.getByTestId('create-tmkey-confirm')
  expect(buttonConfirm).toBeDisabled()

  await act(async () => user.type(rowName, 'my test key'))

  await waitFor(async () => user.click(buttonConfirm))

  rerender(<WrapperComponent {...contextValues} />)

  const rowNewEntry = {
    lookup: getRowElementById({
      column: ROW_COLUMNS_TESTID.LOOKUP,
      id: '6f03df0307c7a161afa9',
    }),
    update: getRowElementById({
      column: ROW_COLUMNS_TESTID.UPDATE,
      id: '6f03df0307c7a161afa9',
    }),
  }

  expect(rowNewEntry.lookup).toBeChecked()
  expect(rowNewEntry.update).toBeChecked()

  // delete
  const menuButton = screen.getByTestId('menu-button-show-items')

  await act(async () => user.click(menuButton))

  const deleteButton = screen.getByTestId('delete-resource')
  await act(async () => user.click(deleteButton))

  const deleteConfirm = screen.queryByText('Confirm')
  expect(deleteConfirm).toBeInTheDocument()

  await waitFor(async () => user.click(deleteConfirm))

  rerender(<WrapperComponent {...contextValues} />)

  const rowDeleted = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: '6f03df0307c7a161afa9',
  })

  expect(rowDeleted).not.toBeInTheDocument()
})

test('Create shared resource', async () => {
  mswServer.use(
    http.post(config.basepath, ({request}) => {
      const url = new URL(request.url)
      const action = url.searchParams.get('action')
      const response =
        action === 'ajaxUtils'
          ? {errors: [], data: [], success: true}
          : {errors: [], data: []}

      return HttpResponse.json(response)
    }),
  )

  const user = userEvent.setup()
  const contextValues = contextMockValues({noTmKeys: true})

  const {rerender} = render(<WrapperComponent {...contextValues} />)

  const button = screen.getByTestId('add-shared-resource-tm')
  expect(button).toBeInTheDocument()

  await act(async () => user.click(button))

  const rowName = screen.getByTestId(SPECIAL_ROWS_ID.addSharedResource)

  expect(rowName).toBeInTheDocument()
  expect(rowName).toHaveFocus()

  const buttonConfirm = screen.getByTestId('create-tmkey-confirm')
  expect(buttonConfirm).toBeDisabled()

  await act(async () => user.type(rowName, 'my test key'))

  expect(buttonConfirm).toBeDisabled()

  await act(async () => user.type(rowName, 'my test key'))

  const rowSharedKey = screen.getByTestId(
    `input-${SPECIAL_ROWS_ID.addSharedResource}`,
  )
  await act(async () => user.type(rowSharedKey, '6a820ef8f06d922ca0a0'))

  expect(buttonConfirm).toBeEnabled()
  await waitFor(async () => user.click(buttonConfirm))

  rerender(<WrapperComponent {...contextValues} />)

  const rowNewEntry = {
    lookup: getRowElementById({
      column: ROW_COLUMNS_TESTID.LOOKUP,
      id: '6a820ef8f06d922ca0a0',
    }),
    update: getRowElementById({
      column: ROW_COLUMNS_TESTID.UPDATE,
      id: '6a820ef8f06d922ca0a0',
    }),
  }

  expect(rowNewEntry.lookup).toBeChecked()
  expect(rowNewEntry.update).toBeChecked()
})

test('Row Menu items', async () => {
  mswServer.use(
    http.post(config.basepath, ({request}) => {
      const url = new URL(request.url)
      const action = url.searchParams.get('action')
      const response =
        action === 'createRandUser'
          ? {
              errors: [],
              data: {
                key: '6f03df0307c7a161afa9',
                id: 'MyMemory_5007d86025f58b50f29c',
                pass: 'f22815f87d',
                mtLangSupported: true,
                error: {
                  code: 0,
                  message: '',
                },
              },
            }
          : {errors: [], data: []}

      return HttpResponse.json(response)
    }),
  )

  const user = userEvent.setup()
  const contextValues = contextMockValues({noTmKeys: true})

  const {rerender} = render(<WrapperComponent {...contextValues} />)

  const button = screen.getByTestId('new-resource-tm')
  await act(async () => user.click(button))

  const rowName = screen.getByTestId(SPECIAL_ROWS_ID.newResource)
  const buttonConfirm = screen.getByTestId('create-tmkey-confirm')

  await act(async () => user.type(rowName, 'my test key'))
  await waitFor(async () => user.click(buttonConfirm))

  rerender(<WrapperComponent {...contextValues} />)

  const menuButton = screen.getByTestId('menu-button-show-items')

  await act(async () => user.click(screen.getByTestId('menu-button')))
  expect(screen.getByText('Select a tmx file to import')).toBeInTheDocument()

  await act(async () => user.click(menuButton))
  await act(async () => user.click(screen.getByTestId('import-glossary')))
  expect(
    screen.getByText('Select glossary in XLSX, XLS or ODS format'),
  ).toBeInTheDocument()

  await act(async () => user.click(menuButton))
  await act(async () => user.click(screen.getByTestId('export-tmx')))
  expect(
    screen.getByText(
      'We will send a link to download the exported TM to your email.',
    ),
  ).toBeInTheDocument()

  await act(async () => user.click(menuButton))
  await act(async () => user.click(screen.getByTestId('export-glossary')))
  expect(
    screen.getByText(
      'We will send a link to download the exported Glossary to your email.',
    ),
  ).toBeInTheDocument()

  await act(async () => user.click(menuButton))
  await act(async () => user.click(screen.getByTestId('share-resource')))
  expect(screen.getByText('Share')).toBeEnabled()
})

test('Pretranslate truthy', async () => {
  const {currentProjectTemplate, ...rest} = contextMockValues()
  const contextValues = {
    ...rest,
    currentProjectTemplate: {
      ...currentProjectTemplate,
      pretranslate100: true,
    },
  }

  render(<WrapperComponent {...contextValues} />)

  expect(screen.getByTestId('pretranslate-checkbox')).toBeChecked()
})

test('Get public matches falsy', async () => {
  const {currentProjectTemplate, ...rest} = contextMockValues()
  const contextValues = {
    ...rest,
    currentProjectTemplate: {
      ...currentProjectTemplate,
      getPublicMatches: false,
    },
  }

  render(<WrapperComponent {...contextValues} />)

  const rowLookup = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: SPECIAL_ROWS_ID.defaultTranslationMemory,
  })

  expect(rowLookup).not.toBeChecked()
})

test('Search resources inactive keys', async () => {
  const user = userEvent.setup()
  const contextValues = contextMockValues()

  render(<WrapperComponent {...contextValues} />)

  const searchInput = screen.getByTestId('search-inactive-tmkeys')

  await act(async () => user.click(searchInput))
  await act(async () => user.type(searchInput, 'myKey'))

  const row1 = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: 'e32699c0a360e08948fe',
  })
  const row2 = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: '74b6c82408a028b6f020',
  })
  const row3 = getRowElementById({
    column: ROW_COLUMNS_TESTID.LOOKUP,
    id: '21df10c8cce1b31f2d0d',
  })
  expect(row1).not.toBeInTheDocument()
  expect(row2).not.toBeInTheDocument()
  expect(row3).toBeInTheDocument()
})

test('Modal delete tmkeys used in other templates', async () => {
  const spyShowModal = jest.spyOn(ModalsActions, 'showModalComponent')

  const user = userEvent.setup()
  const {projectTemplates, ...rest} = contextMockValues({
    tmKeysMockArray: tmKeysMock.tm_keys.filter(
      ({key}) => key === '74b6c82408a028b6f020',
    ),
  })
  const template = {
    ...projectTemplates[1],
    id: 5,
    tm: [projectTemplates[1].tm[0]],
  }
  const contextValues = {
    ...rest,
    projectTemplates: [...projectTemplates, template],
    currentProjectTemplate: template,
  }

  render(<WrapperComponent {...contextValues} />)

  const menuButton = screen.getByTestId('menu-button-show-items')

  await act(async () => user.click(menuButton))

  const deleteButton = screen.getByTestId('delete-resource')
  await act(async () => user.click(deleteButton))

  const modalContent = spyShowModal.mock.calls[0][1].content
  expect(modalContent).toBe(
    'The memory key you are about to delete is used in the following project creation template(s):',
  )
})
