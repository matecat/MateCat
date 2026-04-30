import {AuthToken, Translator} from '@translated/lara'

class LaraTranslator extends Translator {
  async translate(text, source, target, options, callback) {
          const headers = {};

          if (options?.headers) {
              for (const [name, value] of Object.entries(options.headers)) {
                  headers[name] = value;
              }
          }

          if (options?.noTrace) {
              headers["X-No-Trace"] = "true";
          }

          const response = this.client.postAndGetStream(
              "/v2/translate",
              {
                  q: text,
                  source,
                  target,
                  source_hint: options?.sourceHint,
                  content_type: options?.contentType,
                  multiline: options?.multiline !== false,
                  adapt_to: options?.adaptTo,
                  glossaries: options?.glossaries,
                  instructions: options?.instructions,
                  timeout: options?.timeoutInMillis,
                  priority: options?.priority,
                  use_cache: options?.useCache,
                  cache_ttl: options?.cacheTTLSeconds,
                  verbose: options?.verbose,
                  style: options?.style,
                  reasoning: options?.reasoning,
                  metadata: options?.metadata,
                  profanities_detect: options?.profanitiesDetect,
                  profanities_handling: options?.profanitiesHandling,
                  styleguide_id: options?.styleguideId,
                  styleguide_reasoning: options?.styleguideReasoning,
                  styleguide_explanation_language: options?.styleguideExplanationLanguage
              },
              undefined,
              headers
          );

          let lastResult;
          for await (const partial of response) {
              if (options?.reasoning && callback) callback(partial);
              lastResult = partial;
          }

          if (!lastResult) throw new Error("No translation result received.");

          return lastResult;
      }
}

export const laraTranslate = async ({
  token,
  source,
  contextListBefore,
  contextListAfter,
  sid,
  jobId,
  glossaries,
  style,
  reasoning = true,
}) => {
  const credentials = new AuthToken(token, null)

  const lara = new LaraTranslator(credentials, {
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
      reasoning,
      style,
    },
  )
}
