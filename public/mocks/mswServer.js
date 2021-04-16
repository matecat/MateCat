import {setupServer} from 'msw/node'
// import {rest} from 'msw'

const handlers = []

export const mswServer = setupServer(...handlers)
