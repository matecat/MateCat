import getEntities from "./getEntities";

const prepareTextForLexiqa = (editorState) => {
    const currentContent = editorState.getCurrentContent();
    const plainContent = currentContent.getPlainText();
    const entities = getEntities(editorState);
    // sort ascending
    entities.sort((a, b) => {return a.start-b.start});
    // update intervals to absolute
    let prevBlock, currentBlock;
    let prevBlockKey = '';
    let lengthParsed = 0;
    const updatedEntities = entities.map( ent => {
        prevBlockKey = prevBlockKey || ent.blockKey; // assign only whn empty
        prevBlock = prevBlock || currentContent.getBlockForKey(prevBlockKey).getText();
        if(prevBlockKey !== ent.blockKey) {
            lengthParsed += prevBlock.length + 1;
            currentBlock = currentContent.getBlockForKey(ent.blockKey).getText();
            prevBlockKey = ent.blockKey;
            prevBlock = currentBlock;
        }
        return {
            start: ent.start + lengthParsed,
            end: ent.end + lengthParsed
        }
    })
    // start replacing
    let newText = plainContent;
    let replaceCount = 0;
    updatedEntities.forEach( ent => {
        const pre = newText.substring(0, ent.start+ replaceCount);
        const middle = newText.substring(ent.start + replaceCount, ent.end + replaceCount)
        const post = newText.substring(ent.end + replaceCount);
        newText = `${pre}<${middle}>${post}`;
        replaceCount += 2;
    })

    return newText;
}

export default prepareTextForLexiqa;