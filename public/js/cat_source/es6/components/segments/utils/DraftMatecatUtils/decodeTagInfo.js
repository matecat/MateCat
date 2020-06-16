import {tagSignatures} from "./tagModel";
import {getIdAttributeRegEx, unescapeHTML} from "./textUtils";

/**
 *
 * @param tag
 * @returns {{id: string, content: string}}
 */

const decodeTagInfo = (tag) => {
    let decodedTagData = {
        id: '',
        content: ''
    };
    // if Tag is defined
    if(tag.type in tagSignatures) {
        // Catch ID attribute
        const idMatch = getIdAttributeRegEx().exec(tag.data.encodedText);
        if(idMatch && idMatch.length > 1) {
            decodedTagData.id = decodedTagData.id + idMatch[1];
        }
        // Catch Content - if regex exists, try to search, else put placeholder
        if(tagSignatures[tag.type].placeholderRegex){
            const contentMatch = tagSignatures[tag.type].placeholderRegex.exec(tag.data.encodedText);
            if(contentMatch && contentMatch.length > 1) {
                decodedTagData.content =  tagSignatures[tag.type].decodeNeeded ? atob(contentMatch[1]) : contentMatch[1];
                decodedTagData.content = unescapeHTML(decodedTagData.content);
            }else if(tagSignatures[tag.type].placeholder){
                decodedTagData.content = tagSignatures[tag.type].placeholder;
            }
        }else {
            decodedTagData.content = tagSignatures[tag.type].placeholder;
        }
    }else{
        decodedTagData.content = '<>'
    }
    return decodedTagData;
};

export default decodeTagInfo;
