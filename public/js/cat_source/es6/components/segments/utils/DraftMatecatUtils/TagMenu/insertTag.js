import {EditorState, Modifier, SelectionState} from 'draft-js';

const insertTag = (tagSuggestion, editorState, triggerText = null) => {

    let contentState  = editorState.getCurrentContent();

    // Effettua eventualmente il replace del carattere che ha innsescato l'inserimento
    const selectionState = triggerText ? editorState.getSelection().merge({
        anchorOffset: editorState.getSelection().anchorOffset-triggerText.length,
        focusOffset: editorState.getSelection().anchorOffset
    }) : editorState.getSelection();

    const {type, mutability, data} = tagSuggestion;

    // Creo la nuova entità
    contentState = contentState.createEntity(
        type,
        mutability,
        data
    );

    const entityKey = contentState.getLastCreatedEntityKey();
    const inlinestyle = editorState.getCurrentInlineStyle();

    // Sostituisce il contenuto
    const replacedContent = Modifier.replaceText(
        contentState,
        selectionState,
        data.placeholder,
        inlinestyle,
        entityKey);

    // Inserisce il nuovo testo sostituito
    const newEditorState = EditorState.push(
        editorState,
        replacedContent,
        'insert-characters'
    );
    
    return newEditorState;

};


export default insertTag;
