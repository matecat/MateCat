import { getErrorCheckTag } from "../tagModel";

const checkForMissingTags = (sourceTagMap, targetTagMap) => {

    if(!sourceTagMap){
        return {
            missingTags: [],
            sourceTags: []
        }
    }
    // Remove unnecessary tags (nbsp, \t, \r, \n)
    let filteredSourceTagMap = sourceTagMap.filter( tag => {
        return getErrorCheckTag().includes(tag.data.name)
    });
    let filteredTargetTagMap = targetTagMap ? targetTagMap.filter( tag => {
        return getErrorCheckTag().includes(tag.data.name)
    }) : [];

    // Remove IDs, so tags without openTagId or closeTagId will be recognised when inserted while typing
    /*filteredSourceTagMap = filteredSourceTagMap.map( tagInSource => {
        tagInSource.data.openTagId = null
        tagInSource.data.closeTagId = null
        return tagInSource
    })*/

    // Check which source's tags are missing in target
    let missingTagInTarget = filteredSourceTagMap.filter( tagInSource => {
        let found = false;
        const {data: { id: idSourceTag, name: nameSourceTag, decodedText: decodedTextSourceTag}} = tagInSource;
        filteredTargetTagMap.forEach( tagInTarget => {
            const {data: { id: idTargetTag, name: nameTargetTag, decodedText: decodedTextTargetTag}} = tagInTarget;
            // ph tags doesn't have fixed ID from BE, it will be recomputed on every page refresh
            if(nameSourceTag === 'ph' &&  nameSourceTag === nameTargetTag && decodedTextTargetTag === decodedTextSourceTag){
                found = true
            }else if(nameSourceTag !== 'ph' && idTargetTag === idSourceTag && nameTargetTag === nameSourceTag){
                found = true;
            }
        });
        return !found;
    });

    // Sort tag by offset
    missingTagInTarget.sort((a, b) => {return a.offset-b.offset});
    filteredSourceTagMap.sort((a, b) => {return a.offset-b.offset});

    return {
        missingTags: [...missingTagInTarget],
        sourceTags: [...filteredSourceTagMap]
    }
};

export default checkForMissingTags;

