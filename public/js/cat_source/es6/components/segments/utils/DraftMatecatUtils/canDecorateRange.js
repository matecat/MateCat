import {getNoLexiqaTag, getNoGlossaryTag} from "./tagModel";


const canDecorateRange = (rangeStart, rangeEnd, contentBlock, contentState, decoratorName) => {
    let i = rangeStart;
    let canDecorate = true;
    while(i <= rangeEnd){
        // Place conditions here...
        switch (decoratorName) {
            case 'lexiqa':
                if(checkLexiqaConditions(i, contentBlock, contentState)) canDecorate = false;
                break;
            case 'glossary':
                if(checkGlossaryConditions(i, contentBlock, contentState)) canDecorate = false;
                break;
            default:
                break;
        }
        i++;
    }
    return canDecorate;
};

export default canDecorateRange;


// Exclude tag mapped to avoid lexiqa check
const checkLexiqaConditions = (charPosition, contentBlock, contentState) => {
    const entityKey = contentBlock.getEntityAt(charPosition);
    let entityInstance, entityType;
    if(entityKey) {
        entityInstance = contentState.getEntity(entityKey);
        entityType = entityInstance.getType()
    }
    return getNoLexiqaTag().includes(entityType);
};

// Exclude tag mapped to avoid glossary check
const checkGlossaryConditions = (charPosition, contentBlock, contentState) => {
    const entityKey = contentBlock.getEntityAt(charPosition);
    let entityInstance, entityType;
    if(entityKey) {
        entityInstance = contentState.getEntity(entityKey);
        entityType = entityInstance.getType()
    }
    return getNoGlossaryTag().includes(entityType);
};
