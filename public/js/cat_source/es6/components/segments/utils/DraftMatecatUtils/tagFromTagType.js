import {getBuildableTag, tagSignatures, TagStruct} from "./tagModel";

const structFromName = (tagName) => {
    // if tag doesn't exists or is not one of [nbsp,tab, ...]
    // Todo: test offset & originalOffset

    const tagType = tagSignatures[tagName].type;

    if(!getBuildableTag().includes(tagName)) return null;
    let newTagStruct = new TagStruct(
        0,
        tagSignatures[tagName].placeholder.length,
        tagType
    );
    newTagStruct.data.name = tagName;
    newTagStruct.data.encodedText = tagSignatures[tagName].encodedPlaceholder;
    newTagStruct.data.decodedText = tagSignatures[tagName].placeholder;
    newTagStruct.data.placeholder = tagSignatures[tagName].placeholder;
    newTagStruct.data.originalOffset = 0;

    return newTagStruct;
}
export default structFromName;