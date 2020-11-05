/*
 Component: ui.header
 */

$.extend(UI, {
    initHeader: function () {
        if (SearchUtils.searchEnabled)
            $('#action-search').show(100, function () {
                APP.fitText($('#pname-container'), $('#pname'), 25);
            });

        /*if ($('#action-download').length) {
			$('#action-download').dropdown();
		}*/
        if ($('#action-three-dots').length) {
            $('#action-three-dots').dropdown();
        }
        if ($('#user-menu-dropdown').length) {
            $('#user-menu-dropdown').dropdown();
        }

        if (config.isLoggedIn) {
            setTimeout(function () {
                CatToolActions.showHeaderTooltip();
            }, 3000);
        }
        this.renderQualityReportButton();
        this.createJobMenu();
    },
    logoutAction: function () {
        $.post('/api/app/user/logout', function (data) {
            if ($('body').hasClass('manage')) {
                location.href = config.hostpath + config.basepath;
            } else {
                window.location.reload();
            }
        });
    },
    showProfilePopUp: function (openProfileTooltip) {
        if (openProfileTooltip) {
            var self = this;
            var tooltipTex =
                "<h4 class='header'>Manage your projects</h4>" +
                "<div class='content'>" +
                '<p>Click here, then "My projects" to retrieve and manage all the projects you have created in MateCat.</p>' +
                "<a class='close-popup-teams'>Next</a>" +
                '</div>';
            $('header .user-menu-container')
                .popup({
                    on: 'click',
                    onHidden: function () {
                        $('header .user-menu-container').popup('destroy');
                        CatToolActions.setPopupUserMenuCookie();
                        return true;
                    },
                    html: tooltipTex,
                    closable: false,
                    onCreate: function () {
                        $('.close-popup-teams').on('click', function () {
                            $('header .user-menu-container').popup('hide');
                            self.openPopupThreePoints();
                        });
                    },
                    className: {
                        popup: 'ui popup user-menu-tooltip',
                    },
                })
                .popup('show');
        } else {
            this.openPopupThreePoints();
        }
    },
    openPopupThreePoints: function () {
        var closedPopup = localStorage.getItem('infoThreeDotsMenu-' + config.userMail);
        if (!closedPopup) {
            var self = this;
            var tooltipTex =
                "<h4 class='header'>Easier tool navigation and new shortcuts</h4>" +
                "<div class='content'>" +
                '<p>Click here to navigate to:</br>' +
                '- Translate/Revise mode</br>' +
                '- Volume analysis</br>' +
                '- XLIFF-to-target converter</br>' +
                '- Shortcut guide</p>' +
                "<a class='close-popup-teams'>Got it!</a>" +
                '</div>';
            $('#action-three-dots')
                .popup({
                    on: 'click',
                    onHidden: function () {
                        $('#action-three-dots').popup('destroy');
                        CommonUtils.addInStorage('infoThreeDotsMenu-' + config.userMail, true, 'infoThreeDotsMenu');
                        return true;
                    },
                    html: tooltipTex,
                    closable: false,
                    onCreate: function () {
                        $('.close-popup-teams').on('click', function () {
                            $('#action-three-dots').popup('hide');
                            self.openPopupInstructions();
                        });
                    },
                    className: {
                        popup: 'ui popup three-dots-menu-tooltip',
                    },
                })
                .popup('show');
        } else {
            this.openPopupInstructions();
        }
    },
    openPopupInstructions: function () {
        var closedPopup = localStorage.getItem('infoInstructions-' + config.userMail);
        if (!closedPopup && $('#files-instructions > div').length > 0) {
            var tooltipTex =
                "<h4 class='header'>Instructions and references</h4>" +
                "<div class='content'>" +
                '<p>You can view the instructions and references any time by clicking here.</p>' +
                "<a class='close-popup-teams'>Got it!</a>" +
                '</div>';
            $('#files-instructions')
                .popup({
                    on: 'click',
                    onHidden: function () {
                        $('#files-instructions').popup('destroy');
                        CommonUtils.addInStorage('infoInstructions-' + config.userMail, true, 'infoInstructions');
                        return true;
                    },
                    html: tooltipTex,
                    closable: false,
                    onCreate: function () {
                        $('.close-popup-teams').on('click', function () {
                            $('#files-instructions').popup('hide');
                        });
                    },
                    className: {
                        popup: 'ui popup files-instructions-tooltip',
                    },
                })
                .popup('show');
        }
    },
    createJobMenu: function () {
        API.JOB.getJobFilesInfo(config.id_job, config.password).done(function (response) {
            CatToolActions.storeFilesInfo(response);
            var menu =
                '<nav id="jobMenu" class="topMenu">' +
                '<ul class="gotocurrentsegment">' +
                '<li class="currSegment" data-segment="' +
                UI.currentSegmentId +
                '"><a>Go to current segment</a><span>' +
                Shortcuts.cattol.events.gotoCurrent.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase() +
                '</span></li>' +
                '<li class="firstSegment" ><span class="label">Go to first segment of the file:</span></li>' +
                '</ul>' +
                '<div class="separator"></div>' +
                '<ul class="jobmenu-list">';
            var iconTick =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 12">' +
                '<path fill="#FFF" fillRule="evenodd" stroke="none" strokeWidth="1" d="M15.735.265a.798.798 0 00-1.13 0L5.04 9.831 1.363 6.154a.798.798 0 00-1.13 1.13l4.242 4.24a.799.799 0 001.13 0l10.13-10.13a.798.798 0 000-1.129z" transform="translate(-266 -10) translate(266 8) translate(0 2)" />' +
                '</svg>';

            _.forEach(response.files, function (file) {
                menu +=
                    '<li data-file="' +
                    file.id +
                    '" data-segment="' +
                    file.first_segment +
                    '"><span class="' +
                    CommonUtils.getIconClass(file.file_name.split('.')[file.file_name.split('.').length - 1]) +
                    '"></span><a href="javascript: void(0)" title="' +
                    file.file_name +
                    '" >' +
                    file.file_name.substring(0, 20) +
                    iconTick +
                    '</a></li>';
            });

            menu += '</ul>' + '</nav>';
            UI.body.find('#project-badge span').text(response.files.length);
            UI.body.append(menu);

            initEvents();
            UI.detectStartSegment();
            var segment = SegmentStore.getCurrentSegment();
            if (segment) {
                UI.updateJobMenu(segment);
            }
            API.JOB.getJobMetadata(config.id_job, config.password).done( function ( jobMetadata ) {
                var fileInstructions = response.files.find((file) => file.metadata && file.metadata.instructions && file.metadata.instructions !== '');
                var projectInfo = jobMetadata.project && jobMetadata.project.project_info ? jobMetadata.project.project_info : undefined;
                if (fileInstructions || projectInfo) {
                    ReactDOM.render(
                        React.createElement(JobMetadata, {
                            files: response.files,
                            projectInfo: projectInfo
                        }),
                        document.getElementById('files-instructions')
                    );
                }
            });
        });
    },
    updateJobMenu: function (segment) {
        var fileId = segment.id_file;
        $('#jobMenu .jobmenu-list li').removeClass('current');
        $('#jobMenu .jobmenu-list li[data-file=' + fileId + ']').addClass('current');
    },
    renderQualityReportButton: function () {
        CatToolActions.renderQualityReportButton();
        if (config.isReview) {
            UI.reloadQualityReport();
        }
    },
    reloadQualityReport: function () {
        var path = sprintf(APP.getRandomUrl() + 'api/app/jobs/%s/%s/quality-report', config.id_job, config.password);
        $.ajax({
            type: 'GET',
            xhrFields: { withCredentials: true },
            url: path,
        }).done(function (data) {
            CatToolActions.updateQualityReport(data['quality-report']);
        });
    },
});

var initEvents = function () {
    $('#action-search').bind('click', function (e) {
        SearchUtils.toggleSearch(e);
    });
    $('#action-settings').bind('click', function (e) {
        e.preventDefault();
        UI.openOptionsPanel();
    });
    $('.user-menu-container').on('click', '#logout-item', function (e) {
        e.preventDefault();
        UI.logoutAction();
    });
    $('.user-menu-container').on('click', '#manage-item', function (e) {
        e.preventDefault();
        document.location.href = '/manage';
    });
    $('#profile-item').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $('#modal').trigger('openpreferences');
        return false;
    });

    $('#action-three-dots .shortcuts').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        APP.ModalWindow.showModalComponent(ShortCutsModal, null, 'Shortcuts');
        return false;
    });

    $('.action-menu').on('click', '#action-filter', function (e) {
        e.preventDefault();
        if (!SegmentFilter.open) {
            SegmentFilter.openFilter();
        } else {
            SegmentFilter.closeFilter();
            SegmentFilter.open = false;
        }
    });

    $('#jobMenu')
        .on('click', '.jobmenu-list li', function (e) {
            e.preventDefault();
            UI.renderAndScrollToSegment($(this).attr('data-segment'));
        })
        .on('click', 'li.currSegment:not(.disabled)', function (e) {
            e.preventDefault();
            SegmentActions.scrollToCurrentSegment();
            SegmentActions.setFocusOnEditArea();
        });
};
