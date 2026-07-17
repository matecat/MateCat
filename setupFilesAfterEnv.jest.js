import '@testing-library/jest-dom'
import {cleanup} from '@testing-library/react'
import {mswServer} from './public/mocks/mswServer'

beforeAll(() => {
  mswServer.listen({onUnhandledRequest: 'error'})
})

afterEach(() => {
  cleanup()
  jest.clearAllMocks()
  mswServer.resetHandlers()
  jest.clearAllTimers()
})

afterAll(() => {
  mswServer.close()
})
