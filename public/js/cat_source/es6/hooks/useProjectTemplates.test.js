import {renderHook, act, waitFor} from '@testing-library/react'
import projectTemplatesMock from '../../../../mocks/projectTemplateMock'
import tmKeysMock from '../../../../mocks/tmKeysMock'
import useProjectTemplates from './useProjectTemplates'
import {mswServer} from '../../../../mocks/mswServer'
import {HttpResponse, http} from 'msw'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
  isLoggedIn: 1,
}

beforeEach(() => {
  mswServer.use(
    http.get(`${config.basepath}api/v3/project-template/`, () => {
      return HttpResponse.json(projectTemplatesMock)
    }),
  )
})

test('Get templates', async () => {
  const {result} = renderHook(() => useProjectTemplates(tmKeysMock.tm_keys))

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(2)
  })

  const {projectTemplates} = result.current
  expect(projectTemplates.some(({isSelected}) => isSelected)).toBeTruthy()
})

test('Change current template', async () => {
  const {result} = renderHook(() => useProjectTemplates(tmKeysMock.tm_keys))
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
  const {result} = renderHook(() => useProjectTemplates(tmKeysMock.tm_keys))
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
  const {result} = renderHook(() => useProjectTemplates(tmKeysMock.tm_keys))
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
  const {result} = renderHook(() => useProjectTemplates(tmKeysMock.tm_keys))
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

  const {result} = renderHook(() => useProjectTemplates(tmKeysMock.tm_keys))

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(1)
  })

  const {currentProjectTemplate} = result.current

  expect(currentProjectTemplate.id).toBe(0)
})
