import {getBuildableTag, tagSignatures, TagStruct} from "./tagModel";

const structFromType = (tagType) => {
    // if tag doesn't exists or is not one of [nbsp,tab, ...]
    if(!getBuildableTag().includes(tagType)) return null;
    let newTagStruct = new TagStruct(
        0,
        tagSignatures[tagType].placeholder.length,
        tagType
    );
    newTagStruct.data.encodedText = tagSignatures[tagType].encodedPlaceholder;
    newTagStruct.data.decodedText = tagSignatures[tagType].placeholder;
    newTagStruct.data.placeholder = tagSignatures[tagType].placeholder;
    newTagStruct.data.originalOffset = 0;

    return newTagStruct;
}
export default structFromType;