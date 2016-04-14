APP.tryListGDriveFiles = function() {
    $.getJSON('/gdrive/list', function(listFiles){
        $('.files-gdrive').html('');

        if( listFiles && listFiles.hasOwnProperty('files') ) {
            $.each( listFiles.files, function( index, file ) {
                var iconClass = '';

                if ( file.fileExtension == 'docx' ) {
                    iconClass = 'extgdoc';
                } else if ( file.fileExtension == 'pptx' ) {
                    iconClass = 'extgsli';
                } else if ( file.fileExtension == 'xlsx' ) {
                    iconClass = 'extgsheet';
                }

                $('<tr/>', {
                    'class': 'template-gdrive fade ready',
                    'style': 'display: table-row;'
                })
                .append (
                    $('<td/>', {
                        'class': 'preview'
                    })
                    .append (
                        $('<span/>', {
                            'class': iconClass
                        })
                    )
                )
                .append (
                    $('<td/>', {
                        'class': 'name',
                        text: file.fileName
                    })
                )
                .append (
                    $('<td/>', {
                        'class': 'size'
                    })
                    .append (
                        $('<span/>', {
                            text: APP.formatBytes(file.fileSize)
                        })
                    )
                )
                .append (
                    $('<td/>', {
                        'class': 'delete'
                    })
                    .append (
                        $('<button/>', {
                            'class': 'btn btn-dange ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary',
                            'data-fileid': file.fileId,
                            'role': 'button',
                            'aria-disabled': 'false',
                            click: function() {
                                APP.deleteGDriveFile( $(this).data('fileid') );
                            }
                        })
                        .append (
                            $('<span/>', {
                                'class': 'ui-button-icon-primary ui-icon ui-icon-trash'
                            })
                        )
                        .append (
                            $('<span/>', {
                                'class': 'ui-button-text'
                            })
                            .append (
                                $('<i/>', {
                                    'class': 'icon-ban-circle icon-white'
                                })
                            )
                            .append (
                                $('<span/>', {
                                    text: 'Delete'
                                })
                            )
                        )
                    )
                )
                .appendTo('.files-gdrive');
            });
        } else {
            window.open('/', '_self');
        }
    });
};

APP.restartGDriveConversions = function () {
    var sourceLang = $("#source-lang").val();
    
    $.getJSON('/gdrive/change/' + sourceLang, function(response){
        if(response.success) {
            console.log('Source language changed.');
        }
    });
};

APP.deleteGDriveFile = function (fileId) {
    $.getJSON('/gdrive/delete/' + fileId, function(response){
        if(response.success) {
            APP.tryListGDriveFiles();
        }
    });
};

APP.formatBytes = function(bytes,decimals) {
   if(bytes === 0) return '0 Byte';
   var k = 1024;
   var dm = decimals + 1 || 2;
   var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
   var i = Math.floor(Math.log(bytes) / Math.log(k));
   return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

$(document).ready( function() {
    $('#clear-all-gdrive').click( function() {
        APP.deleteGDriveFile('all');
    })
});