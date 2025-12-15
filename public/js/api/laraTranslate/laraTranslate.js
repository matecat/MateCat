import {Translator} from '@translated/lara'

export const laraTranslate = async ({token, source}) => {
  const lara = new Translator({accessKey: token})
  return await lara.translate(source, config.target_rfc, config.source_rfc)
}
