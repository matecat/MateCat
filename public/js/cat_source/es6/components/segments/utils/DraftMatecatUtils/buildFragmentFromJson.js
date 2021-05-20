import {ContentBlock, BlockMapBuilder, CharacterMetadata} from 'draft-js'
import Immutable from 'immutable'

const buildFragmentFromJson = (fragmentObject) => {
  const {OrderedMap, List, OrderedSet} = Immutable

  //const fragmentContent =  JSON.parse(text);
  const fragmentMap = OrderedMap(fragmentObject)

  let blocks = []
  fragmentMap.forEach((block) => {
    // Rebuild CharacterMetadata
    let charsArray = []
    block.characterList.forEach((char) => {
      //const EMPTY_SET = OrderedSet();
      const charStyle = OrderedSet(char.style)
      charsArray.push(
        CharacterMetadata.create({style: charStyle, entity: char.entity}),
      )
    })
    const charList = new List(charsArray)

    // Rebuild ContentBlock
    const newBlock = new ContentBlock({
      key: block.key,
      type: block.type,
      text: block.text,
      characterList: charList,
      depth: block.depth,
      data: block.data,
    })
    // Add
    blocks.push(newBlock)
  })
  // Create fragment
  return BlockMapBuilder.createFromArray(blocks)
}

export default buildFragmentFromJson
