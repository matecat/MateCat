
const prepareTextForLexiqa = (editorState) => {
    const currentContent = editorState.getCurrentContent();
    const plainContent = currentContent.getPlainText();
    let lengthParsed = 0;
    let updatedEntities = []
    const blocks = currentContent.getBlocksAsArray();
    // update intervals to absolute
    blocks.forEach(block => {
        block.findEntityRanges((character) => {
                return character.getEntity() !== null;
        },
        (start, end) => {
            updatedEntities.push({
                start: start + lengthParsed,
                end: end + lengthParsed
            });
        });
        // add parsed content length
        lengthParsed += block.getText().length + 1;
    })

    // wrap every tag in '<' and '>'
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