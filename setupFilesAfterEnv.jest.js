import '@testing-library/jest-dom'
import {mswServer} from './public/mocks/mswServer'

beforeAll(() => {
  mswServer.listen({onUnhandledRequest: 'error'})
})

afterEach(() => {
  mswServer.resetHandlers()
})

afterAll(() => {
  mswServer.close()
})
