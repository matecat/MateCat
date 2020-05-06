import decodeTagInfo from "./decodeTagInfo";
import {TagStruct} from "./tagModel";

/**
 *
 * @param text
 * @param tagSignature
 * @returns {[]} tagRange - array with all occurrences of tagSignature in the input text
 */
const findTagWithRegex = (text, tagSignature) => {
    let matchArr;
    const {type, openRegex, openLength, closeRegex} = tagSignature;
    const tagRange = [];

    while((matchArr = openRegex.exec(text)) !== null){
        const tag = new TagStruct();
        tag.offset = matchArr.index;
        if(!closeRegex) {
            tag.length = openLength;
            tag.data.encodedText = text.slice(tag.offset, tag.offset + tag.length);
        }else {
            let slicedText = text.slice(tag.offset, text.length);
            matchArr = closeRegex.exec(slicedText);
            tag.length = matchArr.index + matchArr[1].length; //Length of previous regex
            tag.data.encodedText = text.slice(tag.offset, tag.offset + tag.length);
        }
        tag.type = type;
        // Todo: maybe decodedText should include other decoded data, like id='mtc_1'
        tag.data.placeholder = decodeTagInfo(tag);
        tag.data.decodedText = decodeTagInfo(tag);
        tagRange.push({...tag});
    }
    return tagRange;
};

export default findTagWithRegex;
