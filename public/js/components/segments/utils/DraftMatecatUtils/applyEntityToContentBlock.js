import {CharacterMetadata} from 'draft-js'

const applyEntityToContentBlock = (contentBlock, start, end, entityKey) => {
  var characterList = contentBlock.getCharacterList()
  while (start < end) {
    characterList = characterList.set(
      start,
      CharacterMetadata.applyEntity(characterList.get(start), entityKey),
    )
    start++
  }
  return contentBlock.set('characterList', characterList)
}

export default applyEntityToContentBlock
