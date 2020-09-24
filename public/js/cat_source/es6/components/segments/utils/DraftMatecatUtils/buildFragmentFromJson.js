import {ContentBlock, BlockMapBuilder, CharacterMetadata} from "draft-js";
import Immutable  from 'immutable';

const buildFragmentFromJson = (fragmentObject) => {
    const {OrderedMap, List, OrderedSet} = Immutable;

    //const fragmentContent =  JSON.parse(text);
    const fragmentMap = new OrderedMap(fragmentObject);

    let blocks = [];
    fragmentMap.forEach((block) => {
        // Rebuild CharacterMetadata
        let charsArray = [];
        block.characterList.forEach(char => {
            const EMPTY_SET = OrderedSet();
            charsArray.push(CharacterMetadata.create({style: EMPTY_SET, entity:char}))
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

export default buildFragmentFromJson;
