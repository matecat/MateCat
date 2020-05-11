import {ContentBlock, BlockMapBuilder, CharacterMetadata} from "draft-js";
import Immutable  from 'immutable';

const buildFragmentFromText = (fragmentObject) => {
    const {OrderedMap, List} = Immutable;

    //const fragmentContent =  JSON.parse(text);
    const fragmentMap = new OrderedMap(fragmentObject);

    let blocks = [];
    fragmentMap.forEach((block) => {
        // Rebuild CharacterMetadata
        let charsArray = [];
        block.characterList.forEach(char => {
            charsArray.push(new CharacterMetadata(char))
        });
        const charList = new List(charsArray);

        // Rebuild ContentBlock
        const newBlock = new ContentBlock({
            key: block.key,
            type: block.type,
            text: block.text,
            characterList: charList,
            depth: block.depth,
            data: block.data,
        });
        // Add
        blocks.push(new ContentBlock(newBlock))
    });
    // Create fragment
    return BlockMapBuilder.createFromArray(blocks);
};

export default buildFragmentFromText;
