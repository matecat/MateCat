/**
 *
 * @param text
 * @param tagSignature
 * @returns {[]} tagRange - array with all occurrences of tagSignature in the input text
 */
const findWithRegex = (text, tagSignature) => {
    let matchArr;
    let entity = {
        offset: -1,
        length: null,
        type: null
    };
    const {type, openRegex, openLength, closeRegex} = tagSignature;
    const tagRange = [];

    while((matchArr = openRegex.exec(text)) !== null){
        entity.offset = matchArr.index;
        if(!closeRegex) {
            entity.length = openLength;
            let originalText = text.slice(entity.offset, entity.offset + entity.length);
            entity.data = {'originalText': originalText, 'openTagId': null, 'openTagKey': null};
        }else {
            let slicedText = text.slice(entity.offset, text.length);
            matchArr = closeRegex.exec(slicedText);
            entity.length = matchArr.index + matchArr[1].length; //Length of previous regex
            let originalText = text.slice(entity.offset, entity.offset + entity.length);
            entity.data = {'originalText': originalText, 'closeTagId': null, 'closeTagKey': null};
        }
        entity.type = type;
        entity.mutability = 'IMMUTABLE';
        tagRange.push({...entity});
    }
    return tagRange;
};

export default findWithRegex;
