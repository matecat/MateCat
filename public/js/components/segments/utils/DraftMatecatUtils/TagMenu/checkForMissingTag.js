import {getErrorCheckTag} from '../tagModel'
import {classifyPcPhTag, createPcNumberer} from '../pcTagUtils'

const isPcTag = (tag) => !!classifyPcPhTag(tag?.data?.encodedText)

// pc (compressible) tags can't be matched by raw content/id like other tags:
// every closing pc tag encodes to the same generic markup (XLIFF's `</pc>` has
// no id), so two different missing closing tags are indistinguishable by
// content alone. Match by identity instead — the dataRef base id when present,
// otherwise the document-order pairing index (the same numbering shown as the
// tag's "1", "2"... label) — combined with open/close role.
const buildPcKeyedEntries = (tagMap) => {
  const numberer = createPcNumberer()
  return [...tagMap]
    .sort((a, b) => a.offset - b.offset)
    .map((tag) => {
      const classified = classifyPcPhTag(tag?.data?.encodedText)
      if (!classified) return null
      const numbered = numberer(tag.data.encodedText)
      const key = classified.hasDataRef
        ? `d:${classified.baseId}:${classified.role}`
        : `s:${numbered?.index}:${classified.role}`
      return {tag, key}
    })
    .filter(Boolean)
}

const checkForMissingTags = (sourceTagMap, targetTagMap) => {
  if (!sourceTagMap) {
    return {
      missingTags: [],
      sourceTags: [],
    }
  }
  // Remove unnecessary tags (nbsp, \t, \r, \n)
  let filteredSourceTagMap = sourceTagMap.filter((tag) => {
    return getErrorCheckTag().includes(tag.data.name)
  })
  let filteredTargetTagMap = targetTagMap
    ? targetTagMap.filter((tag) => {
        return getErrorCheckTag().includes(tag.data.name)
      })
    : []

  const srcPcEntries = buildPcKeyedEntries(filteredSourceTagMap.filter(isPcTag))
  const trgPcKeys = new Set(
    buildPcKeyedEntries(filteredTargetTagMap.filter(isPcTag)).map(
      (entry) => entry.key,
    ),
  )
  const missingPcTags = srcPcEntries
    .filter((entry) => !trgPcKeys.has(entry.key))
    .map((entry) => entry.tag)

  // pc tags are matched above; keep them out of the generic id/decodedText
  // comparison below so they don't get (mis)matched a second time there.
  const nonPcSourceTagMap = filteredSourceTagMap.filter((tag) => !isPcTag(tag))
  const nonPcTargetTagMap = filteredTargetTagMap.filter((tag) => !isPcTag(tag))

  // Remove IDs, so tags without openTagId or closeTagId will be recognised when inserted while typing
  /*filteredSourceTagMap = filteredSourceTagMap.map( tagInSource => {
        tagInSource.data.openTagId = null
        tagInSource.data.closeTagId = null
        return tagInSource
    })*/

  // Remove target tags from source tags
  const arraySubtract = (arr1, arr2) => {
    const arr2Copy = arr2.slice()
    return arr1.filter((sourceEl) => {
      const {
        data: {
          id: idSourceTag,
          name: nameSourceTag,
          decodedText: decodedTextSourceTag,
        },
      } = sourceEl
      const idxToRemove = arr2Copy.findIndex((targetEl) => {
        const {
          data: {
            id: idTargetTag,
            name: nameTargetTag,
            decodedText: decodedTextTargetTag,
          },
        } = targetEl
        if (nameSourceTag === 'ph') {
          return (
            nameTargetTag === 'ph' &&
            ((decodedTextSourceTag &&
              decodedTextSourceTag === decodedTextTargetTag) ||
              (idSourceTag && idTargetTag === idSourceTag))
          )
        }
        return idTargetTag === idSourceTag && nameSourceTag === nameTargetTag
      })
      if (idxToRemove === -1) return true
      arr2Copy.splice(idxToRemove, 1)
    })
  }
  let missingTagInTarget = [
    ...arraySubtract(nonPcSourceTagMap, nonPcTargetTagMap),
    ...missingPcTags,
  ]

  // Sort tag by offset
  missingTagInTarget.sort((a, b) => {
    return a.offset - b.offset
  })
  filteredSourceTagMap.sort((a, b) => {
    return a.offset - b.offset
  })

  return {
    missingTags: [...missingTagInTarget],
    sourceTags: [...filteredSourceTagMap],
  }
}

export default checkForMissingTags
