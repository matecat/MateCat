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

































});


