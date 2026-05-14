import {AuthToken, Translator} from '@translated/lara'

export const laraStyleguides = async ({token}) => {
  const credentials = new AuthToken(token, null)

  const lara = new Translator(credentials, {
    connectionTimeoutMs: 30000,
  })

  return await lara.styleguides.list()
}
