
const updateLexiqaWarnings = (editorState, warnings) => {

    const contentState = editorState.getCurrentContent();
    const blocks = contentState.getBlockMap();
    let maxCharsInBlocks = 0;
    let updatedWarnings = [];

    blocks.forEach((loopedContentBlock) => {
        const lastBlockKey = contentState.getLastBlock().getKey();
        const loopedBlockKey = loopedContentBlock.getKey();
        // Se non è l'ultimo blocco, aggiungi un carattere per indicare il newline
        const newLineChar = loopedBlockKey !== lastBlockKey ? 1 : 0;
        maxCharsInBlocks += newLineChar;
        // Aggiungi la lunghezza del blocco corrente
        maxCharsInBlocks += loopedContentBlock.getLength();

        _.each(warnings, warn => {
            // Todo: warnings between 2 block are now ignored
            if (warn.start < maxCharsInBlocks &&
                warn.end <= maxCharsInBlocks &&
                warn.start >= (maxCharsInBlocks - loopedContentBlock.getLength())) {

                const alreadyScannedChars = maxCharsInBlocks - (loopedContentBlock.getLength() + newLineChar );
                const relativeStart = warn.start - alreadyScannedChars;
                const relativeEnd = warn.end - alreadyScannedChars;

                const warnUpdated = _.cloneDeep(warn);
                warnUpdated.start = relativeStart;
                warnUpdated.end = relativeEnd;
                // Aggiungi la chiave da riutilizzare poi nella strategy del decoratore
                warnUpdated.blockKey = loopedBlockKey;

                updatedWarnings.push(warnUpdated)
            }
        });
    });

    return updatedWarnings;
};

export default updateLexiqaWarnings;
