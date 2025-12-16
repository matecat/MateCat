import {AuthToken, Translator} from '@translated/lara'

export const laraTranslate = async ({token, source}) => {
  const lara = new Translator(new AuthToken(token, null))
  return await lara.translate(source, config.target_rfc, config.source_rfc)
}
