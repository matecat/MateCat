import {tagSignatures, TagStruct} from "./tagModel";
import {unescapeHTML} from "./textUtils";

/**
 *
 * @param tag
 * @returns {string} decodedTagData - Decoded data inside tag
 */
const decodeTagInfo = (tag) => {
    let decodedTagData;
    if(tag.type in tagSignatures) {
        // If regex exists, try to search, else put placeholder
        if(tagSignatures[tag.type].placeholderRegex!== null){
            const idMatch = tagSignatures[tag.type].placeholderRegex.exec(tag.data.encodedText);
            if(idMatch && idMatch.length > 1) {
                decodedTagData =  tagSignatures[tag.type].decodeNeeded ? atob(idMatch[1]) : idMatch[1];
                decodedTagData = unescapeHTML(decodedTagData);
            }else if(tagSignatures[tag.type].placeholder){
                decodedTagData = tagSignatures[tag.type].placeholder;
            }
        }else {
            decodedTagData = tagSignatures[tag.type].placeholder;
        }
    }else{
        decodedTagData = '<unknown/>'
    }
    return decodedTagData;
};

export default decodeTagInfo;
