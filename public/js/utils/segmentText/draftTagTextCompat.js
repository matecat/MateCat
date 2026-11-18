// TEMPORARY: remove this file and its two call sites (api/setTranslation, SegmentStore.normalizeSplittedSegments)
// once the backend keeps typed markup as text: https://app.asana.com/1/991845504084/project/1134617950425092/task/1220665721859271
//
// The editor sends text the translator typed as encoded text (`&lt;g&gt;`), which is right. The backend
// subfiltering then turns that escaped markup into a `ph` tag when the segment is read back, and the QA reports a
// tag mismatch. Until that is fixed, the translation is sent the way the Draft editor sent it: typed text that looks
// like an XLIFF tag goes out unencoded (`<g>`), and comes back unencoded. When segments load, such markup that
// cannot be a real tag is encoded again, so the editor shows it as text and not as a tag chip.
import {tagSignatures} from './tagModel'
import {decodeHtmlEntities, encodeHtmlEntities} from './tagUtils'

// What the Draft editor kept unencoded in typed text (TextUtils.replaceTempTags): no word boundary, so `<example>`
// and `<good>` too.
const DRAFT_TAG_TEXT =
  /&lt;(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk)((?:(?!&lt;|&gt;)[^<>])*?)&gt;/gi
const RAW_DRAFT_TAG_TEXT = /<(\/)*(g|x|bx|ex|bpt|ept|ph|it|mrk)[^<>]*?>/gi
const XLIFF_TAG_NAMES = ['g', 'x', 'bx', 'ex', 'bpt', 'ept', 'ph', 'it', 'mrk']
const KNOWN_TAG_KEYS = ['ph', 'gSc', 'g', 'gCl', 'bx', 'ex', 'x']

const isKnownTag = (markup) =>
  KNOWN_TAG_KEYS.some((key) => {
    const regex = tagSignatures[key]?.regex
    return (
      !!regex &&
      new RegExp(`^(?:${regex.source})$`, regex.flags.replace('g', '')).test(
        markup,
      )
    )
  })

// Markup the translator typed, never a tag of the file: not one of the editor's tags, and either a name that is not
// an XLIFF tag (`<example>`) or an opening XLIFF tag with no attributes (`<g>`; the file's always carry an id).
// Closing tags of XLIFF names stay as they are: they may close a real tag.
const isTypedText = (markup) => {
  if (isKnownTag(markup)) return false
  const [, closing, name, rest] = markup.match(
    /^<(\/*)([^\s/>]*)([^>]*)>$/,
  ) ?? [null, '', '', '']
  if (!XLIFF_TAG_NAMES.includes(name.toLowerCase())) return true
  return !closing && !rest.replace(/\//g, '').trim()
}

/** The translation as the set-translation request sends it: typed tag-like text unencoded, as Draft sent it. */
export const toDraftTranslation = (translation) =>
  typeof translation === 'string'
    ? translation.replace(DRAFT_TAG_TEXT, (text) => decodeHtmlEntities(text))
    : translation

/** A translation from the server, with typed tag-like text that Draft saved unencoded encoded again as text. */
export const fromDraftTranslation = (translation) =>
  typeof translation === 'string'
    ? translation.replace(RAW_DRAFT_TAG_TEXT, (markup) =>
        isTypedText(markup) ? encodeHtmlEntities(markup) : markup,
      )
    : translation
