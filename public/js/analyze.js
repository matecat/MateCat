UI = null;

UI = {
	init: function() {
		this.stopPolling = false;
        this.pollingTime = 1000;
        this.segmentsThreshold = 50000;
		this.noProgressTail = 0;
		this.lastProgressSegments = 0;

        this.quoteResponse = [];

		this.previousQueueSize = 0;

		APP.fitText($('#pid'), $('#pname'), 50);
		$(".subfile .filename").each(function() {
			APP.fitText($(this), $(this), 50);
		})

		this.checkStatus('FAST_OK');
		var sew = $('#standard-equivalent-words .word-number');
		if ((sew.text() != '0') && (sew.text() != ''))
			sew.removeClass('loading');

		var mew = $('#matecat-equivalent-words .word-number');
		if ((mew.text() != '0') && (mew.text() != ''))
			mew.removeClass('loading');

		$(".part3").click(function(e) {
			e.preventDefault();
            $(this).parents('tbody').find(".part3files").toggleClass('open');
            $(".loadingbar").removeClass('start');
		});

        function wordCountTotalOrPayable( job ) {
            var total = 0;
            if ( $('.stat-payable').length > 0 ) {
                total = Number( $('.stat-payable', job).first().text().replace(",", "") );
            }

            if (total == 0) {
                total = Number( $('.stat-total', job).first().text().replace(",", "") );
            }
            return total;
        }

		$("body").on('click', '.dosplit:not(.disabled)', function(e) {

			e.preventDefault();
			var jobContainer = $(this).parents('.jobcontainer');
			var job = jobContainer.find('tbody.tablestats');
			jid = job.attr('data-jid');
			total = wordCountTotalOrPayable( job ) ;
			numsplit = $('.splitselect', jobContainer).first().val();
			wordsXjob = total / numsplit;
			wordsXjob = Math.floor(wordsXjob);
			diff = total - (wordsXjob * numsplit);
			$('.popup-split .error-message').addClass('none');
			$('.popup-split .popup-box .jobs').empty();
			$('.popup-split h1 .jid').attr('data-jid', jid);
			$('.popup-split h1 .jid').attr('data-pwd', $(job).attr('data-pwd'));
			$('.popup-split').removeClass('error-number');
			$('.popup-split #exec-split').removeClass('disabled');
			$('.popup-split h1 .chunks').text(numsplit);
			for (var i = 0; i < numsplit; i++) {
				numw = wordsXjob;
				if (i < diff)
					numw++;

				// '<!-- A: la classe Aprox scompare se viene effettuato il calcolo -->' +
				item = '<li>' +
						'   <div><h4>Part ' + (i + 1) + '</h4></div>' +
						'   <div class="job-details">' +
						'       <div class="job-perc">' +
						'           <p><span class="aprox">Approx. words:</span><span class="correct none">Words:</span></p>' +
						'           <input type="text" class="input-small" value="' + numw + '">' +
						'       </div>' +
						'   </div>' +
						'</li>';

				$('.popup-split .popup-box .jobs').append(item);
			}
			$('.popup-split .total .total-w').attr('data-val', total).text(APP.addCommas(total));

			$('.popup-split').show();
		}).on('click', '.popup-split .btn-cancel', function(e) {
			e.preventDefault();
			$('.popup-split .x-popup').click();
		}).on('click', '.modal .x-popup', function(e) {
			e.preventDefault();
            $( "body").removeClass( "showingOutsourceTo" );
			APP.closePopup();
		}).on('click', '.popup-split .x-popup', function(e) {
			UI.resetSplitPopup();
		}).on('blur', '.popup-split .jobs .input-small', function(e) {
			e.preventDefault();
			UI.performPreCheckSplitComputation();
		}).on('focus', '.popup-split .jobs .input-small', function(e) {
			e.preventDefault();

			//if input area is focused we need to check again
			$('.popup-split .text').text('Check');
			$('.popup-split #exec-split').removeAttr('disabled').removeClass('disabled').removeClass('none');
			$('.popup-split #exec-split-confirm').addClass('none');
			$('span.correct').addClass('none');
			$('span.aprox').removeClass('none');

			UI.performPreCheckSplitComputation(false);

		}).on('mouseover', '.popup-split .wordsum', function() {
			UI.performPreCheckSplitComputation();
		}).on('click', '.popup-split #exec-split', function(e) {
			e.preventDefault();

			//disable check Button if there are an error or the event is triggered
			if ($(this).hasClass('disabled'))
				return false;

			$('.popup-split .error-message').addClass('none');
			$(this).addClass('disabled');
			$('.uploadloader').addClass('visible');
			$('.text').text('Checking');
			UI.checkSplit();

		}).on('click', '.popup-split #exec-split-confirm', function(e) {
			e.preventDefault();
			UI.confirmSplit();
		}).on('click', '.out-link', function(e) {
			this.select();
		}).on('click', '.mergebtn:not(.disabled)', function(e) {
			e.preventDefault();
			APP.confirm({
				name: 'confirmMerge', 
				cancelTxt: 'Cancel', 
//				onCancel: 'cancelMerge', 
				callback: 'confirmMerge', 
				caller: $(this),
				okTxt: 'Continue', 
				msg: "This will cause the merging of all chunks in only one job.<br>This operation cannot be canceled."
			});
		}).on('click', '.downloadAnalysisReport', function(e) {
            e.preventDefault();
            UI.downloadAnalysisReport();
        });


		$("#close").click(function(e) {
			e.preventDefault();
			$(".loadingbar").addClass("closebar");
		});

		$("#popupWrapper .x-popup, #popupWrapper .popup-outer, #popupWrapper .popup a.anonymous").click(function(e) {
			e.preventDefault();
			APP.doRequest({
				data: {
					action: 'ajaxUtils',
					exec: 'stayAnonymous'
				},
				success: function(d) {
					$(".popup-outer").fadeOut();
					$(".popup").fadeOut('fast');
				}
			});
		});

		this.pollData();
	},
	performPreCheckSplitComputation: function(doStringSanitization) {

		ss = 0;
		$('.popup-split .jobs .input-small').each(function() {

			if (doStringSanitization === false) {
				//remove dot or commas from input area and sum them.
				//this place the cursor at the right end of input area so, in focus we want that not happens
				//moreover, string is already sanitized
				$(this).attr('value', $(this).val().replace(/[^0-9\.,]/g, ''));
			}

			ss += parseInt($(this).val());
		});

		diff = ss - parseInt($('.popup-split .total .total-w').attr('data-val'));
		if (diff != 0) {
			$('.popup-split .btnsplit .done').addClass('none');
			$('.popup-split #exec-split').removeClass('none').addClass('disabled').attr('disabled', 'disabled');
			$('.popup-split #exec-split').removeClass('none').addClass('disabled').attr('disabled', 'disabled');
			$('.popup-split #exec-split .text').text('Check');
			$('.popup-split .error-count .curr-w').text(APP.addCommas(ss));
			var dm = (diff < 0) ? 'Words remaining' : 'Words exceeding';
			$('.popup-split .error-count .txt').text(dm);
			$('.popup-split .error-count .diff-w').text(APP.addCommas(Math.abs(diff)));
			$('.popup-split').addClass('error-number');
		} else {
			$('.popup-split #exec-split').removeClass('disabled');
			$('.popup-split').removeClass('error-number');
		}
	},

	checkStatus: function(status) {
		if (config.status == status) {
			$('.loadingbar').removeClass('start');
			this.progressBar(config.totalAnalyzed / config.totalSegments);
		}
	},
	checkSplit: function(job) {

		var ar = new Array();
		$('.popup-split ul.jobs li .input-small').each(function() {
			ar.push(parseInt($(this).val()));
		});

		APP.doRequest({
			data: {
				action: "splitJob",
				exec: "check",
                project_id: config.id_project,
                project_pass: config.password,
				job_id: $('.popup-split h1 .jid').attr('data-jid'),
				job_pass: $('.popup-split h1 .jid').attr('data-pwd'),
				num_split: $('.popup-split h1 .chunks').text(),
				split_values: ar
			},
			success: function(d) {

				var total = $('.popup-split .wordsum .total-w').attr('data-val');
				var prog = 0;
                var val;
				if (!$.isEmptyObject(d.data)) {

                    var editAreaList = $( '.popup-split ul.jobs li .input-small' );

                    editAreaList.each( function(key) {

                        if( typeof d.data.chunks[key] == 'undefined' ) {

                            //get last edit area value
                            val = 0;
                            $( editAreaList[key] ).parents('li').addClass( 'void' );
                            $( editAreaList[key] ).addClass( 'empty' );

                        } else {

                          if ( config.split_based_on_raw_word_count ) {
                            val = parseInt( d.data.chunks[key].raw_word_count );
                          }
                          else {
                            val = parseInt( d.data.chunks[key].eq_word_count );
                          }

                        }

                        $( editAreaList[key] ).attr( 'value', val );

					});

					$('.popup-split .uploadloader').removeClass('visible');
					$('.popup-split .text').text('Confirm');
					$('.popup-split .loader').addClass('none');
					$('.popup-split .done').removeClass('none');
					$('.popup-split .aprox').addClass('none');
					$('.popup-split .correct').removeClass('none');
				}

                UI.performPreCheckSplitComputation();

                if ( (typeof d.errors != 'undefined') && (d.errors.length) ) {

					$('.popup-split .uploadloader').removeClass('visible');
					$('.popup-split .text').text('Check');
					$('.popup-split #exec-split').removeAttr('disabled').removeClass('disabled').removeClass('none');
					$('.popup-split .error-message p').text(d.errors[0].message);
					$('.popup-split .error-message').removeClass('none');

				}

			}
		});
	},
	confirmMerge: function() {
		ob = APP.callerObject;
		$(ob).addClass('disabled');
		var jobContainer = $(ob).parents('.jobcontainer');
		var job = jobContainer.find('tbody.tablestats');
		jid = job.attr('data-jid');
		APP.doRequest({
			data: {
				action: "splitJob",
				exec: "merge",
				project_id: config.id_project,
				project_pass: config.password,
				job_id: jid
			},
			complete: function(d) {
//				console.log('location.reload');
				location.reload();
			}
		});		
	},
	cancelMerge: function() {
//		console.log('cancel callback');
//		$('.modal[data-name=confirmMerge] .x-popup').click();
	},
	confirmSplit: function(job) {
//        console.log('confirm split');

		var ar = new Array();
		$('.popup-split ul.jobs li .input-small').each(function() {
			ar.push(parseInt($(this).val()));
		});

		APP.doRequest({
			data: {
				action: "splitJob",
				exec: "apply",
				project_id: config.id_project,
				project_pass: config.password,
				job_id: $('.popup-split h1 .jid').attr('data-jid'),
				job_pass: $('.popup-split h1 .jid').attr('data-pwd'),
				num_split: $('.popup-split h1 .chunks').text(),
				split_values: ar
			},
			success: function(d) {
//                setTimeout(function(){
				location.reload();
//                },8000);                         
			}
		});
	},
	resetSplitPopup: function() {
		var t = '<a id="exec-split" class="uploadbtn loader">' +
				'    <span class="uploadloader"></span>' +
				'    <span class="text">Check</span>' +
				'</a>' +
				'<a id="exec-split-confirm" class="splitbtn done none">' +
				'    <span class="text">Confirm</span>' +
				'</a>' +
				'<span class="btn fileinput-button btn-cancel right">' +
				'    <span>Cancel</span>' +
				'</span>';
		$('.popup-split .btnsplit').html(t);
	},
	progressBar: function(perc) {
		if (perc == 100)
			return;

		$('#shortloading').hide();
		$('#longloading').show();
		$('#longloading .approved-bar').css('width', perc * 100 + '%');
		$('#longloading .approved-bar').attr('title', 'Analyzing ' + parseInt(perc * 100) + '%');
//        UI.progressPerc = UI.progressPerc + 3;
	},
	displayError: function(error) {
		$('#shortloading').hide();
//		$('.loadingbar').addClass('open');
        $('.loadingbar').removeClass('start');
		$('#longloading .meter').hide();
		$('#longloading p').html(error);
		$('#longloading').show();
	},
	pollData: function() {
		if (this.stopPolling) return;

		var pid = config.id_project;
        var ppassword = config.password ;
		if (config.id_job) {
			data = {
				action: 'getVolumeAnalysis',
				pid: pid,
				jpassword: ppassword
			};
		} else {
			data = {
				action: 'getVolumeAnalysis',
				pid: pid,
				ppassword: ppassword
			};
		}

		APP.doRequest({
			data: data,
			success: function ( d ) {
				if ( d.data) {
					var s = d.data.summary;

					if ( (s.STATUS == 'NEW') || (s.STATUS == '') || s.IN_QUEUE_BEFORE > 0 ) {

						$( '.loadingbar' ).removeClass( 'start' );

						if ( config.daemon_warning ) {

							if ( -1 == config.support_mail.indexOf( '@' ) ) {
								analyzerNotRunningErrorString = 'The analysis seems not to be running. Contact ' + config.support_mail + '.';
							} else {
								analyzerNotRunningErrorString = 'The analysis seems not to be running. Contact <a href="mailto:' + config.support_mail + '">' + config.support_mail + '</a>.';
							}
							UI.displayError( analyzerNotRunningErrorString );

							$( '#standard-equivalent-words .word-number' ).removeClass( 'loading' ).text( $( '#raw-words .word-number' ).text() );
							$( '#matecat-equivalent-words .word-number' ).removeClass( 'loading' ).text( $( '#raw-words .word-number' ).text() );
							return false;

						} else if ( s.IN_QUEUE_BEFORE > 0 ) {
							//increasing number of segments ( fast analysis on another project )
							if ( UI.previousQueueSize <= s.IN_QUEUE_BEFORE ) {
								$( '#shortloading' ).show().html( '<p class="label">There are other projects in queue. Please wait...</p>' );
								$( '#longloading' ).hide();
							} else { //decreasing ( TM analysis on another project )
								if ( !$( '#shortloading .queue' ).length ) {
									$( '#shortloading' ).html( '<p class="label">There are still <span class="number">' + s.IN_QUEUE_BEFORE_PRINT + '</span> segments in queue. Please wait...</p>' );
								} else {
									$( '#shortloading .queue .number' ).text( s.IN_QUEUE_BEFORE_PRINT );
								}
							}
						}
						UI.previousQueueSize = s.IN_QUEUE_BEFORE;
					}
					else if ( s.STATUS == 'FAST_OK' && s.IN_QUEUE_BEFORE == 0 ) {

						if ( UI.lastProgressSegments != s.SEGMENTS_ANALYZED ) {

							UI.lastProgressSegments = s.SEGMENTS_ANALYZED;
							UI.noProgressTail = 0;

						} else {

							UI.noProgressTail++;
							if ( UI.noProgressTail > 9 ) {
								if ( -1 == config.support_mail.indexOf( '@' ) ) {
									analyzerNotRunningErrorString = 'The analysis seems not to be running. Contact ' + config.support_mail + '.';
								} else {
									analyzerNotRunningErrorString = 'The analysis seems not to be running. Contact <a href="mailto:' + config.support_mail + '">' + config.support_mail + '</a> or try refreshing the page.';
								}
								UI.displayError( analyzerNotRunningErrorString );
								return false;
							}

						}
						UI.progressBar( s.SEGMENTS_ANALYZED / s.TOTAL_SEGMENTS );
						$( '#analyzedSegmentsReport' ).text( s.SEGMENTS_ANALYZED_PRINT );
						$( '#totalSegmentsReport' ).text( s.TOTAL_SEGMENTS_PRINT );

					}

					var standard_words = $( '#standard-equivalent-words .word-number' );
					old_standard_words = standard_words.text();

					newSText = '';
					if ( s.STATUS == 'DONE' || s.TOTAL_STANDARD_WC > 0 ) {
						standard_words.removeClass( 'loading' );
						$( '#standard-equivalent-words .days' ).show();
						newSText = s.TOTAL_STANDARD_WC_PRINT;
					}
					else {
						$( '#standard-equivalent-words .days' ).hide();
					}
					standard_words.text( newSText );
					if ( (old_standard_words != s.TOTAL_STANDARD_WC_PRINT) && (old_standard_words != '') )
						$( '#standard-equivalent-words .box' ).effect( "highlight", {}, 1000 );
					$( '#standard-equivalent-words .workDays' ).text( s.STANDARD_WC_TIME );
					$( '#standard-equivalent-words .unit' ).text( s.STANDARD_WC_UNIT );

					var matecat_words = $( '#matecat-equivalent-words .word-number' );
					old_matecat_words = matecat_words.text();
					newMText = '';
					if ( s.STATUS == 'DONE' || s.TOTAL_PAYABLE > 0 ) {
						matecat_words.removeClass( 'loading' );
						$( '#matecat-equivalent-words .days' ).show();
						newMText = s.TOTAL_PAYABLE_PRINT;
					} else {
						$( '#matecat-equivalent-words .days' ).hide();
					}

					matecat_words.text( newMText );
					if ( (old_matecat_words != s.TOTAL_PAYABLE_PRINT) && (old_matecat_words != '') )
						$( '#matecat-equivalent-words .box' ).effect( "highlight", {}, 1000 );
					$( '#matecat-equivalent-words .workDays' ).text( s.PAYABLE_WC_TIME );
					$( '#matecat-equivalent-words .unit' ).text( s.PAYABLE_WC_UNIT );

					//					if ( s.DISCOUNT_WC > 0) {
					//						$('.promo-text span').text(s.DISCOUNT_WC);
					//						$('.promo-text').show();
					//					} else {
					//						$('.promo-text').hide();
					//						$('.promo-text span').text(s.DISCOUNT_WC);s
					//					}

					$( '#usageFee' ).text( s.USAGE_FEE );
					$( '#pricePerWord' ).text( s.PRICE_PER_WORD );
					$( '#discount' ).text( s.DISCOUNT );
					$( '#totalFastWC' ).text( s.TOTAL_FAST_WC_PRINT );
					$( '#totalTMWC' ).text( s.TOTAL_PAYABLE_PRINT );

					try {

						$.each( d.data.jobs, function ( job_id, group ) {

							var files_group = group.chunks;
							var total_group = group.totals;

							global_context = $( '#job-' + job_id );

							$.each( total_group, function ( jPassword, tot ) {

								context = $( global_context ).find( ".tablestats[data-pwd='" + jPassword + "']" ).find( '.totaltable' );

								var s_total = $( '.stat-payable', context );
								s_total_txt = s_total.text();

								s_total.text( tot.TOTAL_PAYABLE[1] );
								if ( s_total_txt != s.TOTAL_TM_WC_PRINT )
									s_total.effect( "highlight", {}, 1000 );

								var s_new = $( '.stat_new', context );
								var s_new_txt = s_new.text();
								s_new.text( tot.NEW[1] );
								if ( s_new_txt != tot.NEW[1] )
									s_new.effect( "highlight", {}, 1000 );

								var s_rep = $( '.stat_rep', context );
								s_rep_txt = s_rep.text();
								s_rep.text( tot.REPETITIONS[1] );
								if ( s_rep_txt != tot.REPETITIONS[1] )
									s_rep.effect( "highlight", {}, 1000 );

								var s_int = $( '.stat_int', context );
								s_int_txt = s_int.text();
								s_int.text( tot.INTERNAL_MATCHES[1] );
								if ( s_int_txt != tot.INTERNAL_MATCHES[1] )
									s_int.effect( "highlight", {}, 1000 );

								var s_tm50 = $( '.stat_tm50', context );
								s_tm50_txt = s_tm50.text();
								s_tm50.text( tot.TM_50_74[1] );
								if ( s_tm50_txt != tot.TM_50_74[1] )
									s_tm50.effect( "highlight", {}, 1000 );

								var s_tm75 = $( '.stat_tm75', context );
								s_tm75_txt = s_tm75.text();
								s_tm75.text( tot.TM_75_99[1] );
								if ( s_tm75_txt != tot.TM_75_99[1] )
									s_tm75.effect( "highlight", {}, 1000 );

								var s_tm75_84 = $( '.stat_tm75_84', context );
								s_tm75_84_txt = s_tm75_84.text();
								s_tm75_84.text( tot.TM_75_84[1] );
								if ( s_tm75_84_txt != tot.TM_75_84[1] )
									s_tm75_84.effect( "highlight", {}, 1000 );

								var s_tm85_94 = $( '.stat_tm85_94', context );
								s_tm85_94_txt = s_tm75_84.text();
								s_tm85_94.text( tot.TM_85_94[1] );
								if ( s_tm85_94_txt != tot.TM_85_94[1] )
									s_tm85_94.effect( "highlight", {}, 1000 );

								var s_tm95_99 = $( '.stat_tm95_99', context );
								s_tm95_99_txt = s_tm75_84.text();
								s_tm95_99.text( tot.TM_95_99[1] );
								if ( s_tm95_99_txt != tot.TM_95_99[1] )
									s_tm95_99.effect( "highlight", {}, 1000 );

								var s_tm100 = $( '.stat_tm100', context );
								s_tm100_txt = s_tm100.text();
								s_tm100.text( tot.TM_100[1] );
								if ( s_tm100_txt != tot.TM_100[1] )
									s_tm100.effect( "highlight", {}, 1000 );

								var s_tm100_public = $( '.stat_tm100_public', context );
								s_tm100_public_txt = s_tm100.text();
								s_tm100_public.text( tot.TM_100_PUBLIC[1] );
								if ( s_tm100_public_txt != tot.TM_100_PUBLIC[1] )
									s_tm100_public.effect( "highlight", {}, 1000 );

								var s_tmic = $( '.stat_tmic', context );
								s_tmic_txt = s_tmic.text();
								s_tmic.text( tot.ICE[1] );
								if ( s_tmic_txt != tot.ICE[1] )
									s_tmic.effect( "highlight", {}, 1000 );

								var s_mt = $( '.stat_mt', context );
								s_mt_txt = s_mt.text();
								s_mt.text( tot.MT[1] );
								if ( s_mt_txt != tot.MT[1] )
									s_mt.effect( "highlight", {}, 1000 );


							} );

							$.each( files_group, function ( jPassword, files_object ) {

								$.each( files_object, function ( id_file, file_details ) {

									context = $( global_context ).find( '#file_' + job_id + '_' + jPassword + '_' + id_file );

									var s_payable = $( '.stat_payable strong', context );
									var s_payable_txt = s_payable.text();
									s_payable.text( file_details.TOTAL_PAYABLE[1] );
									if ( s_payable_txt != file_details.TOTAL_PAYABLE[1] )
										s_payable.effect( "highlight", {}, 1000 );

									var s_new = $( '.stat_new', context );
									var s_new_txt = s_new.text();
									s_new.text( file_details.NEW[1] );
									if ( s_new_txt != file_details.NEW[1] )
										s_new.effect( "highlight", {}, 1000 );

									var s_rep = $( '.stat_rep', context );
									s_rep_txt = s_rep.text();
									s_rep.text( file_details.REPETITIONS[1] );
									if ( s_rep_txt != file_details.REPETITIONS[1] )
										s_rep.effect( "highlight", {}, 1000 );

									var s_int = $( '.stat_int', context );
									s_int_txt = s_int.text();
									s_int.text( file_details.INTERNAL_MATCHES[1] );
									if ( s_int_txt != file_details.INTERNAL_MATCHES[1] )
										s_int.effect( "highlight", {}, 1000 );

									var s_tm50 = $( '.stat_tm50', context );
									s_tm50_txt = s_tm50.text();
									s_tm50.text( file_details.TM_50_74[1] );
									if ( s_tm50_txt != file_details.TM_50_74[1] )
										s_tm50.effect( "highlight", {}, 1000 );

									var s_tm75 = $( '.stat_tm75', context );
									s_tm75_txt = s_tm75.text();
									s_tm75.text( file_details.TM_75_99[1] );
									if ( s_tm75_txt != file_details.TM_75_99[1] )
										s_tm75.effect( "highlight", {}, 1000 );

									var s_tm75_84 = $( '.stat_tm75_84', context );
									s_tm75_84_txt = s_tm75_84.text();
									s_tm75_84.text( file_details.TM_75_84[1] );
									if ( s_tm75_84_txt != file_details.TM_75_84[1] )
										s_tm75_84.effect( "highlight", {}, 1000 );

									var s_tm85_94 = $( '.stat_tm85_94', context );
									s_tm85_94_txt = s_tm75_84.text();
									s_tm85_94.text( file_details.TM_85_94[1] );
									if ( s_tm85_94_txt != file_details.TM_85_94[1] )
										s_tm85_94.effect( "highlight", {}, 1000 );

									var s_tm95_99 = $( '.stat_tm95_99', context );
									s_tm95_99_txt = s_tm75_84.text();
									s_tm95_99.text( file_details.TM_95_99[1] );
									if ( s_tm95_99_txt != file_details.TM_95_99[1] )
										s_tm95_99.effect( "highlight", {}, 1000 );

									var s_tm100 = $( '.stat_tm100', context );
									s_tm100_txt = s_tm100.text();
									s_tm100.text( file_details.TM_100[1] );
									if ( s_tm100_txt != file_details.TM_100[1] )
										s_tm100.effect( "highlight", {}, 1000 );

									var s_tm100_public = $( '.stat_tm100_public', context );
									s_tm100_public_txt = s_tm100_public.text();
									s_tm100_public.text( file_details.TM_100_PUBLIC[1] );
									if ( s_tm100_public_txt != file_details.TM_100_PUBLIC[1] )
										s_tm100_public.effect( "highlight", {}, 1000 );

									var s_tmic = $( '.stat_tmic', context );
									s_tmic_txt = s_tmic.text();
									s_tmic.text( file_details.ICE[1] );
									if ( s_tmic_txt != file_details.ICE[1] )
										s_tmic.effect( "highlight", {}, 1000 );

									var s_mt = $( '.stat_mt', context );
									s_mt_txt = s_mt.text();
									s_mt.text( file_details.MT[1] );
									if ( s_mt_txt != file_details.MT[1] )
										s_mt.effect( "highlight", {}, 1000 );

								} );

							} );

						} );

					} catch ( e ) {
						//do Nothing and try again in next poll
					}

                    if( d.data.summary.STATUS == 'DONE' ){

                        $( '#longloading .approved-bar' ).css( 'width', '100%' );
                        $( '#analyzedSegmentsReport' ).text( s.SEGMENTS_ANALYZED_PRINT );

                        precomputeOutsourceQuotes( $( '.uploadbtn.translate' ) );

                        setTimeout( function () {
                            $( '#shortloading' ).remove();
                            $( '#longloading .meter' ).remove();
                            $( '#longloading' ).show();
                            $( '#longloading p' ).addClass( 'loaded' ).html( '<span class="complete">Analysis complete</span>' )
								.append( '<a class="downloadAnalysisReport">Download Analysis Report</a>' );
                            $( '.splitbtn' ).removeClass( 'disabled' ).attr( 'title', '' );
                        }, 1000 );

                    } else if( d.data.summary.STATUS == 'NOT_TO_ANALYZE' ){

                        var rwc = $( '#raw-words' );

                        var sew = $( '#standard-equivalent-words' );
                        sew.find('.word-number').removeClass( 'loading' ).text( rwc.find('.word-number').text() );
                        sew.find('.days').html( rwc.find('.days' ).html() ).show();

                        var mew = $( '#matecat-equivalent-words' );
                        mew.find('.word-number').removeClass( 'loading' ).text( rwc.find('.word-number').text() );
                        mew.find('.days').html( rwc.find('.days' ).html() ).show();

                        precomputeOutsourceQuotes( $( '.uploadbtn.translate' ) );

                        $( '#shortloading' ).remove();
                        $( '#longloading .meter' ).remove();
                        $( '#longloading' ).show();
                        $( '#longloading p' ).addClass( 'loaded' ).html( '<span class="complete">This job is too big.</span>' )
								.append( '<span class="analysisNotPerformed">The analysis was not performed.</span>' );
                        $( '.splitbtn' ).removeClass( 'disabled' ).attr( 'title', '' );

                    } else{

                        if ( d.data.summary.TOTAL_SEGMENTS > UI.segmentsThreshold ) {
                            UI.pollingTime = parseInt( d.data.summary.TOTAL_SEGMENTS / 20 );
                            console.log( 'Polling time: ' + UI.pollingTime );
                        }

                        setTimeout( function () {
                            UI.pollData();
                        }, UI.pollingTime );

                    }

				}

			}

		} );

	},
    downloadAnalysisReport: function () {
        var pid = config.id_project ;
        var ppassword = config.password ;

        var form =  '			<form id="downloadAnalysisReportForm" action="/" method="post">' +
                    '				<input type=hidden name="action" value="downloadAnalysisReport">' +
                    '				<input type=hidden name="id_project" value="' + pid + '">' +
                    '				<input type=hidden name="password" value="' + ppassword + '">' +
                    '				<input type=hidden name="download_type" value="XTRF">' +
                    '			</form>';
        $('body').append(form);
        $('#downloadAnalysisReportForm').submit();

    }
}

function fit_text_to_container(container, child) {
	if (typeof (child) != 'undefined') {
		a = $(child, container).text();
	} else {
		a = container.text();
	}
	w = container.width(); //forse non serve

	first_half = a[0];
	last_index = a.length - 1;
	last_half = a[last_index];


	if (typeof (child) != 'undefined') {
		$(child, container).text(first_half + "..." + last_half);
	} else {
		container.text(first_half + "..." + last_half);
	}

	h = container.height();
	hh = $(child, container).height();

	for (var i = 1; i < a.length; i = i + 1) {
		old_first_half = first_half;
		old_last_half = last_half;

		first_half = first_half + a[i];
		last_half = a[last_index - i] + last_half;


		if (typeof (child) != 'undefined') {
			$(child, container).text(first_half + "..." + last_half);
		} else {
			container.text(first_half + "..." + last_half);
		}
		h2 = container.height();

		if (h2 > h) {
			if (typeof (child) != 'undefined') {
				$(child, container).text(old_first_half + "..." + last_half);
			} else {
				container.text(old_first_half + "..." + last_half);
			}
			h2 = $(container).height();

			if (h2 > h) {
				if (typeof (child) != 'undefined') {
					$(child, container).text(old_first_half + "..." + old_last_half);
				} else {
					container.text(old_first_half + "..." + old_last_half);
				}
			}
			break;
		}
		if ($(child, container).text() == a) {
			break;
		}
	}
}

// as soon as the analysis is done, start pre-fetching outsources quotes
function precomputeOutsourceQuotes( elementsToAskQuoteFor ) {
    // if no elements left to ask quote for then return
    if( elementsToAskQuoteFor.length == 0 ) {
        return;
    }

    getOutsourceQuote( $( elementsToAskQuoteFor.splice( 0, 1 ) ), function( quoteData ) {
        // remember whether outsource popup should be rendered compressed (0) or expanded (1)

        if ( quoteData.data )  {
            UI.showPopupDetails = quoteData.data[0][0].show_info;
            // recursively call self with the remaining elements ( Array.splice(0,1) has already reduced the size )
            precomputeOutsourceQuotes( elementsToAskQuoteFor );
        }
    });
}

$(document).ready(function() {
	APP.init();
	if (config.showModalBoxLogin == 1) {
	    $('#popupWrapper').fadeToggle();
	}
	$('#sign-in').click(function(e) {
		e.preventDefault();
		APP.googole_popup($(e.target).data('oauth'));
		});
	UI.init();
	if ( config.enable_outsource ) {
		UI.outsourceInit();
	}
});
