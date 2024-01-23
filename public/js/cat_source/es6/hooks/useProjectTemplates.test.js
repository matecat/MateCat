import {renderHook, act} from '@testing-library/react'
import tmKeysMock from '../../../../mocks/tmKeysMock'
import useProjectTemplates from './useProjectTemplates'
import {mswServer} from '../../../../mocks/mswServer'
import {HttpResponse, http} from 'msw'

let {tm_keys: tmKeys} = tmKeysMock
const setTmKeys = (value) => {
  const result = typeof value === 'function' ? value(tmKeys) : value
  tmKeys = result
}

// mswServer.use(...[
//     http.post(config.basepath, () => {
//       return HttpResponse.json(payload)
//     }),
// ])

// test('Get templates', async () => {
//   const {result} = renderHook(() => useProjectTemplates({tmKeys, setTmKeys}))

//   expect(result.projectTemplates.length).toBe(2)
// })
