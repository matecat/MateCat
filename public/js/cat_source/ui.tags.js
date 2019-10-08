/*
	Component: ui.tags
 */
$.extend(UI, {

    toggleTagsMode: function () {
        if (UI.body.hasClass('tagmode-default-compressed')) {
            this.setExtendedTagMode();
        } else {
            this.setCrunchedTagMode();
        }
    },

    setTagMode: function () {
        if(this.custom.extended_tagmode) {
            this.setExtendedTagMode();
        } else {
            this.setCrunchedTagMode();
        }
    },
    setExtendedTagMode: function () {
        this.body.removeClass('tagmode-default-compressed');
        $(".tagModeToggle").addClass('active');
        this.custom.extended_tagmode = true;
        this.saveCustomization();
    },
    setCrunchedTagMode: function () {
        this.body.addClass('tagmode-default-compressed');
        $(".tagModeToggle").removeClass('active');
        this.custom.extended_tagmode = false;
        this.saveCustomization();
    },


















    /**
     * Remove all xliff source tags from the string
     * @param currentString : the string to parse
     * @returns the decoded String
     */
    removeAllTags: function (currentString) {
        if (currentString) {
            var regExp = TagUtils.getXliffRegExpression();
            currentString =  currentString.replace(regExp, '');
            return TagUtils.decodePlaceholdersToText(currentString);
        } else {
            return '';
        }
    },

    checkXliffTagsInText: function (text) {
        var reg = TagUtils.getXliffRegExpression();
        return reg.test(text);
    },
    /**
     *
     * This function is used before the text is sent to the server or to transform editArea content.
     * @return Return a cloned element without tag inside
     *
     * @param context
     * @param selector
     * @returns {*|jQuery}
     */
    postProcessEditarea: function(context, selector) {
        selector = (typeof selector === "undefined") ? UI.targetContainerSelector() : selector;
        var area = $( selector, context ).clone();
        area = this.transformPlaceholdersHtml(area);

        area.find('span.space-marker').replaceWith(' ');
        area.find('span.rangySelectionBoundary').remove();
        area = this.encodeTagsWithHtmlAttribute(area);
        return view2rawxliff( area.text() );
    },

    prepareTextToSend: function (text) {
        var div =  document.createElement('div');
        var $div = $(div);
        $div.html(text);
        $div = this.transformPlaceholdersHtml($div);

        $div.find('span.space-marker').replaceWith(' ');
        $div.find('span.rangySelectionBoundary').remove();
        $div = this.encodeTagsWithHtmlAttribute($div);
        return $div.text();
    },

    /**
     * It does the same as postProcessEditarea function but does not remove the cursor span
     * @param text
     * @returns {*}
     */

    cleanTextFromPlaceholdersSpan: function (text) {
        var div =  document.createElement('div');
        var $div = $(div);
        $div.html(text);
        $div = this.transformPlaceholdersHtml($div);
        $div.find('span.space-marker').replaceWith(' ');
        $div = this.encodeTagsWithHtmlAttribute($div);
        return $div.text();
    },

    transformPlaceholdersHtml: function ($elem) {
        var divs = $elem.find( 'div' );

        if( divs.length ){
            divs.each(function(){
                $(this).find( 'br:not([class])' ).remove();
                $(this).prepend( $('<span class="placeholder">' + config.crPlaceholder + '</span>' ) ).replaceWith( $(this).html() );
            });
        } else {
            $elem.find( 'br:not([class])' ).replaceWith( $('<span class="placeholder">' + config.crPlaceholder + '</span>') );
            $elem.find('br.' + config.crlfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crlfPlaceholder + '</span>' );
            $elem.find('span.' + config.lfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.lfPlaceholder + '</span>' );
            $elem.find('span.' + config.crPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crPlaceholder + '</span>' );
        }

        $elem.find('span.' + config.tabPlaceholderClass).replaceWith(config.tabPlaceholder);
        $elem.find('span.' + config.nbspPlaceholderClass).replaceWith(config.nbspPlaceholder);

        return $elem;
    },

    /**
     * This function is called to return the tag inside ph attribute 'equiv-text' to base64
     * @param $elem
     * @returns {*}
     */
    encodeTagsWithHtmlAttribute: function ($elem) {
        $elem.find('.inside-attribute').remove();
        return $elem;
    },

    handleCopyEvent: function ( e ) {
        var elem = $(e.target);
        var cloneTag, text;
        if ( elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute') ) {
            var tag = (elem.hasClass('inside-attribute')) ? elem.parent('span.locked') : elem.parent().parent('span.locked');
            cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            text = cloneTag.text();
            e.clipboardData.setData('text/plain', text.trim());
            e.preventDefault();
        } else if (elem.hasClass('locked')) {
            cloneTag = elem.clone();
            cloneTag.find('.inside-attribute').remove();
            text = htmlEncode(cloneTag.text());
            e.clipboardData.setData('text/plain', text.trim());
            e.clipboardData.setData('text/html', text.trim());
            e.preventDefault();
        }
    },
    handleDragEvent: function ( e ) {
        var elem = $(e.target);
        if ( elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute') ) {
            var tag = elem.closest('span.locked:not(.inside-attribute)');
            var cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            var text = htmlEncode(cloneTag.text());
            e.dataTransfer.setData('text/plain', TagUtils.transformTextForLockTags(text).trim());
            e.dataTransfer.setData('text/html', TagUtils.transformTextForLockTags(text).trim());
        } else if (elem.hasClass('locked')) {
            var text = htmlEncode(elem.text());
            e.dataTransfer.setData('text/plain', TagUtils.transformTextForLockTags(text).trim());
            e.dataTransfer.setData('text/html', TagUtils.transformTextForLockTags(text).trim());
        }
    },
    /**
     * When you click on a tag, it is selected and the selected class is added (ui.events->382).
     * Clicking on the edititarea to remove the tags with the selected class that otherwise are
     * removed the first time you press the delete key (ui.editarea-> 51 )
     */
    removeSelectedClassToTags: function (  ) {
        if (UI.editarea) {
            UI.editarea.find('.locked.selected').removeClass('selected');
            $('.editor .source .locked').removeClass('selected');
        }
    }

});


