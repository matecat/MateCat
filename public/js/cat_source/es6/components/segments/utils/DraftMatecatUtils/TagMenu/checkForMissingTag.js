import {getErrorCheckTag} from "../tagModel";

const checkForMissingTags = (sourceTagMap, targetTagMap) => {

    if(!sourceTagMap){
        return {
            missingTags: [],
            sourceTags: []
        }
    }
    // Rimuovo i tag non necessari (nbsp, \t, \r, \n)
    let filteredSourceTagMap = sourceTagMap.filter( tag => {
        return getErrorCheckTag().includes(tag.data.name)
    });
    let filteredTargetTagMap = targetTagMap ? targetTagMap.filter( tag => {
        return getErrorCheckTag().includes(tag.data.name)
    }) : [];

    // Annullo gli id, i tag senza openTagId o closeTagId verranno riconosciuti quando inseriti a posteriori
    /*filteredSourceTagMap = filteredSourceTagMap.map( tagInSource => {
        tagInSource.data.openTagId = null
        tagInSource.data.closeTagId = null
        return tagInSource
    })*/

    // Controlla quali tag del source non sono nel target
    let missingTagInTarget = filteredSourceTagMap.filter( tagInSource => {
        let notFound = true;
        filteredTargetTagMap.forEach( tagInTarget => {
            if(tagInTarget.data.id === tagInSource.data.id && tagInTarget.data.name === tagInSource.data.name){
                notFound = false;
            }
        });
        return notFound;
    });
/*

    // Prendo gli id delle chiusure presenti nei missing tag (quelle che hanno openTagId)
    // in modo ordinato in base agli offset, e rimuovo quelli che assegno dai missing tag
    let availableKey = missingTagInTarget
        .filter(tag => tag.data.openTagId)
        .map(tag =>{ return tag.data.id})
        .reverse();


    // Ad ogni chiusura non referenziata, assegno il primo id disponibile tra quelli delle chiusure mancanti
    let reassignedIds = [];
    filteredTargetTagMap.forEach( (unreferencedClosure, index) => {
        if(availableKey.length > 0 && !unreferencedClosure.data.id){
            const id = availableKey.pop();
            reassignedIds.push(id)
            console.log('Reassigning the key #', id)
            filteredTargetTagMap[index].data.id = id;
        }
    });

    //ritorna tutti TRANNE le chiusure riassegnate (quelli con openTagId e data.id contenuto in reassignedIds)
    missingTagInTarget = missingTagInTarget.filter( missingTag => {
        return !(reassignedIds.includes(missingTag.data.id) &&  missingTag.data.openTagId)
    });
*/
    // Sort tag by offset
    missingTagInTarget.sort((a, b) => {return a.offset-b.offset});
    filteredSourceTagMap.sort((a, b) => {return a.offset-b.offset});
    //checkTags
    return {
        missingTags: [...missingTagInTarget],
        sourceTags: [...filteredSourceTagMap]
    }
};

export default checkForMissingTags;

