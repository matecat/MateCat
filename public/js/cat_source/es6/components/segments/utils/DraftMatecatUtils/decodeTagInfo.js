import tagStruct from "./tagStruct";
import {unescapeHTML} from "./textUtils";

/**
 *
 * @param tag
 * @returns {string} decodedTagData - Decoded data inside tag
 */
const decodeTagInfo = (tag) => {
    let decodedTagData;
    if(tag.type in tagStruct) {
        // If regex exists, try to search, else put placeholder
        if(tagStruct[tag.type].placeholderRegex!== null){
            const idMatch = tagStruct[tag.type].placeholderRegex.exec(tag.data.originalText);
            if(idMatch && idMatch.length > 1) {
                decodedTagData =  tagStruct[tag.type].decodeNeeded ? atob(idMatch[1]) : idMatch[1];
                decodedTagData = unescapeHTML(decodedTagData);
            }else if(tagStruct[tag.type].placeholder){
                decodedTagData = tagStruct[tag.type].placeholder;
            }
        }else {
            decodedTagData = tagStruct[tag.type].placeholder;
        }
    }else{
        decodedTagData = '<unknown/>'
    }
    return decodedTagData;
};

export default decodeTagInfo;
