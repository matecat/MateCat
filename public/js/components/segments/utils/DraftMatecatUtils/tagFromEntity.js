import {TagStruct} from './tagModel'

const tagFromEntity = (tagEntity) => {
  const tagEntityInstance = tagEntity.entity
  const {
    id,
    name,
    originalOffset,
    openTagId,
    encodedText,
    closeTagId,
    placeholder,
    decodedText,
  } = tagEntityInstance.getData()

  const tag = new TagStruct(
    originalOffset,
    encodedText.length,
    tagEntityInstance.type,
  )
  tag.data.encodedText = encodedText
  tag.data.id = id
  tag.data.name = name
  tag.data.placeholder = placeholder
  tag.data.decodedText = decodedText
  tag.data.openTagId = openTagId
  tag.data.closeTagId = closeTagId

  return tag
}

export default tagFromEntity
