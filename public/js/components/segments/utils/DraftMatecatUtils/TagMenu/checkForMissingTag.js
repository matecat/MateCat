import {getErrorCheckTag} from '../tagModel'

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
          index,
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
        return nameTargetTag === 'ph' && index === undefined
          ? decodedTextSourceTag === decodedTextTargetTag &&
              nameSourceTag === nameTargetTag
          : idTargetTag === idSourceTag && nameSourceTag === nameTargetTag
      })
      if (idxToRemove === -1) return true
      arr2Copy.splice(idxToRemove, 1)
    })
  }
  let missingTagInTarget = arraySubtract(
    filteredSourceTagMap,
    filteredTargetTagMap,
  )

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
