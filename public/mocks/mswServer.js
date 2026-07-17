import {setupServer} from 'msw/node'
const handlers = []

export const mswServer = setupServer(...handlers)
