import {renderHook, act, waitFor} from '@testing-library/react'
import projectTemplatesMock from '../../mocks/projectTemplateMock'
import tmKeysMock from '../../mocks/tmKeysMock'
import useProjectTemplates, {
  STANDARD_TEMPLATE,
  UseProjectTemplateInterface,
  useProjectTemplateInterface,
} from './useProjectTemplates'
import {mswServer} from '../../mocks/mswServer'
import {HttpResponse, http} from 'msw'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
  isLoggedIn: 1,
}

beforeEach(() => {
  mswServer.use(
    ...[
      http.get(`${config.basepath}api/app/project-template/default`, () => {
        return HttpResponse.json(projectTemplatesMock.items[0])
      }),
      http.get(`${config.basepath}api/v3/project-template/`, () => {
        return HttpResponse.json({
          items: projectTemplatesMock.items.filter(({id}) => id !== 0),
        })
      }),
    ],
  )
})

test('Get templates', async () => {
  const {result} = renderHook(() =>
    useProjectTemplates({tmKeys: tmKeysMock.tm_keys, mtEngines: []}),
  )

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(2)
  })

  const {projectTemplates} = result.current
  expect(projectTemplates.some(({isSelected}) => isSelected)).toBeTruthy()
})

test('Change current template', async () => {
  const {result} = renderHook(() =>
    useProjectTemplates({tmKeys: tmKeysMock.tm_keys, mtEngines: []}),
  )
  const {setProjectTemplates} = result.current

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(2)
  })

  act(() =>
    setProjectTemplates((prevState) =>
      prevState.map((template) => ({
        ...template,
        isSelected: template.id === 3,
      })),
    ),
  )
  const {currentProjectTemplate} = result.current
  expect(currentProjectTemplate?.id).toBe(3)
})

test('Modyfing current template', async () => {
  const {result} = renderHook(() =>
    useProjectTemplates({tmKeys: tmKeysMock.tm_keys, mtEngines: []}),
  )
  const {modifyingCurrentTemplate} = result.current

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(2)
  })

  act(() => {
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      getPublicMatches: false,
    }))
  })

  let {currentProjectTemplate} = result.current
  expect(currentProjectTemplate.isTemporary).toBeTruthy()
  expect(currentProjectTemplate.getPublicMatches).toBeFalsy()

  act(() =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      getPublicMatches: true,
    })),
  )

  currentProjectTemplate = result.current.currentProjectTemplate
  expect(currentProjectTemplate.isTemporary).toBeFalsy()
  expect(currentProjectTemplate.getPublicMatches).toBeTruthy()
})

test('Modifyng current template with wrong prop', async () => {
  const {result} = renderHook(() =>
    useProjectTemplates({tmKeys: tmKeysMock.tm_keys, mtEngines: []}),
  )
  const {modifyingCurrentTemplate} = result.current

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(2)
  })

  expect(() => {
    act(() =>
      modifyingCurrentTemplate((prevTemplate) => ({
        ...prevTemplate,
        customProp: false,
      })),
    )
  }).toThrow('Invalid prop customProp.')
})

test('Check is modified specific property', async () => {
  const {result} = renderHook(() =>
    useProjectTemplates({tmKeys: tmKeysMock.tm_keys, mtEngines: []}),
  )
  const {modifyingCurrentTemplate, checkSpecificTemplatePropsAreModified} =
    result.current

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(2)
  })

  act(() =>
    modifyingCurrentTemplate((prevTemplate) => ({
      ...prevTemplate,
      pretranslate100: true,
    })),
  )

  expect(
    checkSpecificTemplatePropsAreModified(['get_public_matches']),
  ).toBeFalsy()

  expect(
    checkSpecificTemplatePropsAreModified(['pretranslate_100']),
  ).toBeTruthy()
})

test('Cattool page', async () => {
  global.config.is_cattool = true

  const {result} = renderHook(() =>
    useProjectTemplates({tmKeys: tmKeysMock.tm_keys, mtEngines: []}),
  )

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(1)
  })

  const {currentProjectTemplate} = result.current

  expect(currentProjectTemplate.id).toBe(0)
})

test('STANDARD_TEMPLATE defaults icu_enabled to true', () => {
  expect(STANDARD_TEMPLATE.icu_enabled).toBe(true)
})

// uber and airbnb assign over the member on this singleton from their upload
// extension. It is imported by name above, so dropping the export breaks this
// whole file rather than failing silently in the browser.
describe('useProjectTemplateInterface', () => {
  afterEach(() => {
    delete useProjectTemplateInterface.getCharacterCounterMode
    UseProjectTemplateInterface.prototype.getCharacterCounterMode =
      function () {}
  })

  test('core sets no counter mode', () => {
    expect(
      useProjectTemplateInterface.getCharacterCounterMode(),
    ).toBeUndefined()
  })

  test('a plugin assigning on the object is seen', () => {
    useProjectTemplateInterface.getCharacterCounterMode = () => 'all_one'

    expect(useProjectTemplateInterface.getCharacterCounterMode()).toBe(
      'all_one',
    )
  })

  test('a plugin patching the prototype is still seen', () => {
    UseProjectTemplateInterface.prototype.getCharacterCounterMode = () =>
      'exclude_cjk'

    expect(useProjectTemplateInterface.getCharacterCounterMode()).toBe(
      'exclude_cjk',
    )
  })
})
