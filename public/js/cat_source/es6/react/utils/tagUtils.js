let TAGS_UTILS =  {
    /**
     * Called when a Segment string returned by server has to be visualized, it replace placeholders with tags
     * @param str
     * @returns {XML|string}
     */
    decodePlaceholdersToText: function (str) {
        let _str = str;
        // if(UI.markSpacesEnabled) {
        //     if(jumpSpacesEncode) {
        //         _str = this.encodeSpacesAsPlaceholders(htmlDecode(_str), true);
        //     }
        // }

        _str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'"><br /></span>' )
            .replace( config.crPlaceholderRegex, '<span class="monad marker softReturn' + config.crPlaceholderClass +'"><br /></span>' )
        _str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crPlaceholderRegex, '<span class="monad marker softReturn' + config.crPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crlfPlaceholderRegex, '<br class="' + config.crlfPlaceholderClass +'" />' )
            .replace( config.tabPlaceholderRegex, '<span class="tab-marker monad marker ' + config.tabPlaceholderClass +'" contenteditable="false">&#8677;</span>' )
            .replace( config.nbspPlaceholderRegex, '<span class="nbsp-marker monad marker ' + config.nbspPlaceholderClass +'" contenteditable="false">&nbsp;</span>' )
            .replace(/(<\/span\>)$/gi, "</span><br class=\"end\">"); // For rangy cursor after a monad marker

        return _str;
    },

    transformTextForLockTags: function ( tx ) {
        var brTx1 = "<_plh_ contenteditable=\"false\" class=\"locked style-tag \">$1</_plh_>";
        var brTx2 =  "<span contenteditable=\"false\" class=\"locked style-tag\">$1</span>";

        tx = tx.replace( /&amp;/gi, "&" )
            .replace( /<span/gi, "<_plh_" )
            .replace( /<\/span/gi, "</_plh_" )
            .replace( /&lt;/gi, "<" )
            .replace( /(<(ph.*?)\s*?\/&gt;)/gi, brTx1 )
            .replace( /(<(g|x|bx|ex|bpt|ept|ph.*?|it|mrk)\sid[^<“]*?&gt;)/gi, brTx1 )
            .replace( /(<(ph.*?)\sid[^<“]*?\/>)/gi, brTx1 )
            .replace( /</gi, "&lt;" )
            .replace( /\&lt;_plh_/gi, "<span" )
            .replace( /\&lt;\/_plh_/gi, "</span" )
            .replace( /\&lt;lxqwarning/gi, "<lxqwarning" )
            .replace( /\&lt;\/lxqwarning/gi, "</lxqwarning" )
            .replace( /\&lt;div\>/gi, "<div>" )
            .replace( /\&lt;\/div\>/gi, "</div>" )
            .replace( /\&lt;br\>/gi, "<br />" )
            .replace( /\&lt;br \/>/gi, "<br />" )
            .replace( /\&lt;mark /gi, "<mark " )
            .replace( /\&lt;\/mark/gi, "</mark" )
            .replace( /\&lt;ins /gi, "<ins " ) // For translation conflicts tab
            .replace( /\&lt;\/ins/gi, "</ins" ) // For translation conflicts tab
            .replace( /\&lt;del /gi, "<del " ) // For translation conflicts tab
            .replace( /\&lt;\/del/gi, "</del" ) // For translation conflicts tab
            .replace( /\&lt;br class=["\'](.*?)["\'][\s]*[\/]*(\&gt;|\>)/gi, '<br class="$1" />' )
            .replace( /(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, brTx2 );


        tx = tx.replace( /(<span contenteditable="false" class="[^"]*"\>)(:?<span contenteditable="false" class="[^"]*"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>" );
        tx = tx.replace( /(<\/span\>)$(\s){0,}/gi, "</span> " );
        tx = this.transformTagsWithHtmlAttribute(tx);
        // tx = tx.replace( /(<\/span\>\s)$/gi, "</span><br class=\"end\">" );  // This to show the cursor after the last tag, moved to editarea component
        return tx;
    },

    /**
     * Used to transform special ph tags that may contain html within the equiv-text attribute.
     * Example: &lt;ph id="2" equiv-text="base64:Jmx0O3NwYW4gY2xhc3M9JnF1b3Q7c3BhbmNsYXNzJnF1b3Q7IGlkPSZxdW90OzEwMDAmcXVvdDsgJmd0Ow=="/&gt;
     * The attribute is encoded in base64
     * @param tx
     * @returns {*}
     */
    transformTagsWithHtmlAttribute: function (tx) {
        var returnValue = tx;
        try {
            if (tx.indexOf('locked-inside') > -1) return tx;
            var base64Array=[];
            var phIDs =[];
            tx = tx.replace( /&quot;/gi, '"' );

            tx = tx.replace( /&lt;ph.*?id="(.*?)"/gi, function (match, text) {
                phIDs.push(text);
                return match;
            });

            tx = tx.replace( /&lt;ph.*?equiv-text="base64:.*?"(.*?\/&gt;)/gi, function (match, text) {
                return match.replace(text, "<span contenteditable='false' class='locked locked-inside tag-html-container-close' >\"" + text + "</span>");
            });
            tx = tx.replace( /base64:(.*?)"/gi , function (match, text) {
                base64Array.push(text);
                var id = phIDs.shift();
                return "<span contenteditable='false' class='locked locked-inside inside-attribute' data-original='base64:" + text+ "'><a>("+ id + ")</a>" + Base64.decode(text) + "</span>";
            });
            tx = tx.replace( /(&lt;ph.*?equiv-text=")/gi, function (match, text) {
                var base = base64Array.shift();
                return "<span contenteditable='false' class='locked locked-inside tag-html-container-open' >" + text + "base64:" + base + "</span>";
            });
            // delete(base64Array);
            returnValue = tx;
        } catch (e) {
            console.error("Error parsing tag ph in transformTagsWithHtmlAttribute function");
            returnValue = "";
        } finally {
            return returnValue;
        }


    },

};
module.exports =  TAGS_UTILS;