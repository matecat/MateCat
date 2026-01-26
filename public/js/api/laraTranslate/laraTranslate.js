import {AuthToken, Translator} from '@translated/lara'

export const laraTranslate = async ({
  token,
  source,
  contextListBefore,
  contextListAfter,
  sid,
  jobId,
  glossaries,
}) => {
  const credentials = new AuthToken(token, null)

  const lara = new Translator(credentials, {
    connectionTimeoutMs: 30000,
  })
  let textBlocks = [
    ...contextListBefore.map((item) => {
      return {text: item, translatable: false}
    }),
    {text: source, translatable: true},
    ...contextListAfter.map((item) => {
      return {text: item, translatable: false}
    }),
  ]
  return await lara.translate(
    textBlocks,
    config.source_rfc,
    config.target_rfc,
    {
      multiline: false,
      contentType: 'application/xliff+xml',
      headers: {'X-Lara-Engine-Tuid': `${jobId}:${sid}`},
      glossaries: glossaries,
      reasoning: true,
    },
  )
}
