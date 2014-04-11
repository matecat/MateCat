UI = null;

UI = {
	init: function() {
		this.stopPolling = false;
        this.pollingTime = 1000;
        this.segmentsThreshold = 50000;
		this.noProgressTail = 0;
		this.lastProgressSegments = 0;

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

		//        fit_text_to_container($("#pname"));

		$(".more").click(function(e) {
			e.preventDefault();
			$(".content").toggle();
		});
		$(".more-table").click(function(e) {
			e.preventDefault();
			$(".content-table").toggle();
		});
		$(".outsourceto .uploadbtn").click(function(e) {
			e.preventDefault();
//			$('.outsourcemodal .x-popup').trigger('click');
			UI.showPrices();
		});

		$(".uploadbtn:not(.in-popup)").click(function(e) {
			if(config.enable_outsource) {
				e.preventDefault();
//				$('.outsourcemodal code').text(window.location.protocol + '//' + window.location.host + $(this).attr('href'));
				$('.outsourcemodal input.out-link').val(window.location.protocol + '//' + window.location.host + $(this).attr('href'));
				$('.outsourcemodal .uploadbtn').attr('href', $(this).attr('href'));
				$('.outsourcemodal').show();
				
				return false;
			}
		});
		$(".outsourcemodal").on('click', '.chunks input', function(e) {
			e.stopPropagation();
			UI.setPrices();
		}).on('click', '.outsourcemodal .x-popup', function(e) {
			UI.showOutsourceChoice();
		}).on('click', '.chunks td.outs', function(e) {
			e.stopPropagation();
			ch = $(this).find('input');
			if($(ch).attr('checked')) {
				$(ch).removeAttr('checked');
			} else {
				$(ch).attr('checked', 'checked');
			}
			UI.setPrices();
		}).on('click', '.back', function(e) {
			e.preventDefault();
			UI.showOutsourceChoice();
		});

		/*        
		 $(".part1").click(function(e){
		 e.preventDefault();
		 $(".part1files").toggle();
		 });
		 $(".part2").click(function(e){
		 e.preventDefault();
		 $(".part2files").toggle();
		 });
		 $(".part4").click(function(e){
		 e.preventDefault();
		 $(".part4files").toggle();
		 });
		 */
		$(".part3").click(function(e) {
			e.preventDefault();
			$(this).parents('tbody').find(".part3files").toggleClass('open');
		});
		/*        
		 $(".split").click(function(e){
		 e.preventDefault();
		 $(".grayed").toggle();
		 $(".split-box").toggle();
		 $("body").addClass("popup-opened");
		 
		 });
		 
		 // still used?
		 $(".split-box .uploadbtn, .close, .grayed").click(function(e){
		 e.preventDefault();
		 $(".grayed").toggle();
		 $(".split-box").toggle();
		 $("body").removeClass("popup-opened");
		 });
		 */
		$("body").on('click', '.dosplit:not(.disabled)', function(e) {

			e.preventDefault();
			var jobContainer = $(this).parents('.jobcontainer');
			var job = jobContainer.find('tbody.tablestats');
			jid = job.attr('data-jid');
			total = $('.stat-payable', job).first().text().replace(",", "");
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
		$(".stopbtn").click(function(e) {
			e.preventDefault();
			$(this).toggleClass('stopped');
			if ($(this).hasClass('stopped')) {
				tt = 'Restart';
				act = 'cancel';
				UI.stopPolling = true;
			} else {
				tt = 'Cancel';
				act = 'restart';
				UI.stopPolling = false;
				UI.pollData();
			}

			//	    	tt = ($(this).hasClass('stopped'))? 'Restart' : 'Cancel';
			//	    	act = ($(this).hasClass('stopped'))? 'cancel' : 'restart';
			$(this).text(tt);
			APP.doRequest({
				data: {
					action: 'pauseResume',
					pid: $('#pid').data('pid'),
					act: act
				},
				success: function(d) {
				}
			});

		});

		this.pollData();
		this.checkSticky();
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
	showOutsourceChoice: function() {
		$('.outsourcemodal h1').text('Here is the link to your new translation job');
		$('.outsourcemodal section.outs').hide();
		$('.outsourcemodal section.choose').show();
	},
	setPrices: function() {
		total = 0;
		$('.outsourcemodal .chunks tr:not(.thead):has(input:checked)').each(function() {
			total += parseFloat($(this).attr('data-price'));
		})
		$('.outsourcemodal .total span').text(total.toFixed(2));
		$('.outsourcemodal .delivery span').text(UI.getFarthestDate());
	},

	showPrices: function() {
		$('.outsourcemodal h1').text('Outsource to Translated');
		$('.outsourcemodal section.choose').hide();
		$('.outsourcemodal section.outs').show();

		if(!$('.outsourcemodal .popup-box .chunks tr').length) {
			$('.outsourcemodal .popup-box .chunks').empty();
			rows = '<tr class="thead"><th>Source</th><th>Target</th><th>ID</th><th># words</th><th>Outsource</th></tr>';
			$('.jobcontainer').each(function() {
				source_lang = $(this).find('h3 .source_lang').text();
				target_lang = $(this).find('h3 .target_lang').text();
				$(this).find('.totaltable').each(function() {
					rows += '<tr data-cid="' + $(this).find('.splitnum').text() + '" data-price=""><td class="source">' + source_lang + '</td><td class="target">' + target_lang + '</td><td class="cid">' + $(this).find('.splitnum').text() + '</td><td class="words">' + $(this).find('.stat-payable').text() + '</td><td class="outs"><input type="checkbox" checked="checked" /></td></tr>';
				})					
			})
			$('.outsourcemodal .chunks').append(rows);
		}
		APP.doRequest({
			data: {
				action: 'outsourceToTranslated',
				pid: $('#pid').attr('data-pid')
			},
			error: function() {
//						UI.failedConnection(0, 'outsourceToTranslated');
			},
			success: function(d) {
				UI.showOutsourceData($.parseJSON(d.data));
			}
		});
	},
	getFarthestDate: function() {
		farthest = new Date(0);
		$('.outsourcemodal .chunks tr:not(.thead):has(input[checked=checked])').each(function() {
			dd = new Date($(this).attr('data-delivery'));
			if(dd.getTime() > farthest.getTime()) farthest = dd;
		})
		return $.format.date(farthest, "D MMMM") + ' at ' + $.format.date(farthest, "hh:mm a") + ' (GMT+1)';
	},

	checkStatus: function(status) {
		if (config.status == status) {
			$('.loadingbar').addClass('open');
//            this.progressBar(UI.progressPerc);
			this.progressBar(config.totalAnalyzed / config.totalSegments);
		}
	},
	checkSplit: function(job) {
//        console.log('split');

		var ar = new Array();
		$('.popup-split ul.jobs li .input-small').each(function() {
			ar.push(parseInt($(this).val()));
		});

		APP.doRequest({
			data: {
				action: "splitJob",
				exec: "check",
				project_id: $('#pid').attr('data-pid'),
				project_pass: $('#pid').attr('data-pwd'),
				job_id: $('.popup-split h1 .jid').attr('data-jid'),
				job_pass: $('.popup-split h1 .jid').attr('data-pwd'),
				num_split: $('.popup-split h1 .chunks').text(),
				split_values: ar
			},
			success: function(d) {
				var total = $('.popup-split .wordsum .total-w').attr('data-val');
				var prog = 0;
				if (!$.isEmptyObject(d.data)) {

                    var editAreaList = $( '.popup-split ul.jobs li .input-small' );

                    editAreaList.each( function(key) {

                        if( typeof d.data.chunks[key] == 'undefined' ) {

                            //get last edit area value
                            val = 0;
                            $( editAreaList[key] ).parents('li').addClass( 'void' );
                            $( editAreaList[key] ).addClass( 'empty' );

                        } else {
                            val = parseInt( d.data.chunks[key].eq_word_count ); //this is d.data.chunks[ index ]
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
				project_id: $('#pid').attr('data-pid'),
				project_pass: $('#pid').attr('data-pwd'),
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
				project_id: $('#pid').attr('data-pid'),
				project_pass: $('#pid').attr('data-pwd'),
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
		$('.loadingbar').addClass('open');
		$('#longloading .meter').hide();
		$('#longloading p').html(error);
		$('#longloading').show();
	},
	checkSticky: function() {
		if (!!$('.sticky').offset()) { // make sure ".sticky" element exists
			var stickyTop = $('.sticky').offset().top; // returns number 
			$(window).scroll(function() { // scroll event
				var windowTop = $(window).scrollTop(); // returns number 
				if (stickyTop < windowTop) {
					$('.sticky').css({
						position: 'fixed',
						top: 50,
						left: 0
					});
				} else {
					$('.sticky').css('position', 'static');
				}
			});
		}
	},
	showOutsourceData: function(d) {
		var chunks = d;
//		var chunks = d.chunks;
		$.each(chunks, function(index) {
			$('.outsourcemodal .chunks tr[data-cid=' + this.id + ']').attr('data-price', this.price).attr('data-delivery', this.delivery_date);
		});
		total = 0;
		$('.outsourcemodal .chunks tr:not(.thead):has(input[checked=checked])').each(function() {
			total += parseFloat($(this).attr('data-price'));
		})
		
		$('.outsourcemodal .delivery span').text(UI.getFarthestDate());
		$('.outsourcemodal .total span').text(total.toFixed(2));
	},
	pollData: function() {
		if (this.stopPolling)
			return;
		var pid = $("#pid").attr("data-pid");

        if( typeof $("#pid").attr("data-pwd") === 'undefined' ){
            var jpassword =  $('tbody.tablestats' ).attr('data-pwd');
        }

		var ppassword = $("#pid").attr("data-pwd");

		APP.doRequest({
			data: {
				action: 'getVolumeAnalysis',
				pid: pid,
                ppassword: ppassword,
                jpassword: jpassword
			},
			success: function(d) {
				if (d.data) {
					var s = d.data.summary;
					if ((s.STATUS == 'NEW') || (s.STATUS == '') || s.IN_QUEUE_BEFORE > 0) {
						$('.loadingbar').addClass('open');
						if (s.IN_QUEUE_BEFORE > 0) {
							//increasing number of segments ( fast analysis on another project )
							if ( UI.previousQueueSize <= s.IN_QUEUE_BEFORE ) {
                                $('#shortloading' ).show().html('<p class="label">There are other project in queue. Please wait...</p>');
                                $('#longloading' ).hide();
							} else { //decreasing ( TM analysis on another project )
								if ( !$('#shortloading .queue').length ) {
									$('#shortloading').html('<p class="label">There are still <span class="number">' + s.IN_QUEUE_BEFORE_PRINT + '</span> segments in queue. Please wait...</p>');
								} else {
									$('#shortloading .queue .number').text(s.IN_QUEUE_BEFORE_PRINT);
								}
							}
						}
						UI.previousQueueSize = s.IN_QUEUE_BEFORE;
					}

//                    this is not used, for now we never get an empty status from controller
//                    else if(s.STATUS == 'EMPTY') {
//                        UI.displayError('This project contains 0 segments. Nothing to analyze and translate. May be scanned file?');
//                        return false;
//                    }

					else if (s.STATUS == 'FAST_OK' && s.IN_QUEUE_BEFORE == 0) {
//                        UI.progressBar(UI.progressPerc)
						if (UI.lastProgressSegments != s.SEGMENTS_ANALYZED) {
							UI.lastProgressSegments = s.SEGMENTS_ANALYZED;
							UI.noProgressTail = 0;
						} else {
							UI.noProgressTail++;
							if (UI.noProgressTail > 9) {
								//$('#longloading .meter').hide();
								//$('#longloading p').html('The analyzer seems to have a problem. Contact <a href="mailto:antonio@translated.net">antonio@translated.net</a> or try refreshing the page.');
								UI.displayError('The analyzer seems to have a problem. Contact <a href="mailto:antonio@translated.net">antonio@translated.net</a> or try refreshing the page.');
								return false;
							}
						}
						UI.progressBar(s.SEGMENTS_ANALYZED / s.TOTAL_SEGMENTS);
						$('#analyzedSegmentsReport').text(s.SEGMENTS_ANALYZED_PRINT);
						$('#totalSegmentsReport').text(s.TOTAL_SEGMENTS_PRINT);

					}

					var standard_words = $('#standard-equivalent-words .word-number');
					old_standard_words = standard_words.text();

					newSText = '';
					if (s.TOTAL_STANDARD_WC > 0) {
						standard_words.removeClass('loading');
						$('#standard-equivalent-words .days').show();
						newSText = s.TOTAL_STANDARD_WC_PRINT;
					}
					else {
						$('#standard-equivalent-words .days').hide();
					}
					standard_words.text(newSText);
					if ((old_standard_words != s.TOTAL_STANDARD_WC_PRINT) && (old_standard_words != ''))
						$('#standard-equivalent-words .box').effect("highlight", {}, 1000);
					$('#standard-equivalent-words .workDays').text(s.STANDARD_WC_TIME);
					$('#standard-equivalent-words .unit').text(s.STANDARD_WC_UNIT);

					var matecat_words = $('#matecat-equivalent-words .word-number');
					old_matecat_words = matecat_words.text();
					newMText = '';
					if (s.TOTAL_PAYABLE > 0) {
						matecat_words.removeClass('loading');
						$('#matecat-equivalent-words .days').show();
						newMText = s.TOTAL_PAYABLE_PRINT;
					} else {
						$('#matecat-equivalent-words .days').hide();
					}

					matecat_words.text(newMText);
					if ((old_matecat_words != s.TOTAL_PAYABLE_PRINT) && (old_matecat_words != ''))
						$('#matecat-equivalent-words .box').effect("highlight", {}, 1000);
					$('#matecat-equivalent-words .workDays').text(s.PAYABLE_WC_TIME);
					$('#matecat-equivalent-words .unit').text(s.PAYABLE_WC_UNIT);

					if (s.DISCOUNT_WC > 0) {
						$('.promo-text span').text(s.DISCOUNT_WC);
						$('.promo-text').show();
					} else {
						$('.promo-text').hide();
						$('.promo-text span').text(s.DISCOUNT_WC);
					}
					$('#usageFee').text(s.USAGE_FEE);
					$('#pricePerWord').text(s.PRICE_PER_WORD);
					$('#discount').text(s.DISCOUNT);
					$('#totalFastWC').text(s.TOTAL_FAST_WC_PRINT);
					$('#totalTMWC').text(s.TOTAL_PAYABLE_PRINT);


					$.each(d.data.jobs, function(job_id, group) {

						var files_group = group.chunks;
						var total_group = group.totals;

						global_context = $('#job-' + job_id);

						$.each(total_group, function(jPassword, tot) {

							context = $(global_context).find(".tablestats[data-pwd='" + jPassword + "']").find('.totaltable');

							var s_total = $('.stat-payable', context);
							s_total_txt = s_total.text();
							s_total.text(tot.TOTAL_PAYABLE[1]);
							if (s_total_txt != s.TOTAL_TM_WC_PRINT)
								s_total.effect("highlight", {}, 1000);

							var s_new = $('.stat_new', context);
							var s_new_txt = s_new.text();
							s_new.text(tot.NEW[1]);
							if (s_new_txt != tot.NEW[1])
								s_new.effect("highlight", {}, 1000);

							var s_rep = $('.stat_rep', context);
							s_rep_txt = s_rep.text();
							s_rep.text(tot.REPETITIONS[1]);
							if (s_rep_txt != tot.REPETITIONS[1])
								s_rep.effect("highlight", {}, 1000);

							var s_int = $('.stat_int', context);
							s_int_txt = s_int.text();
							s_int.text(tot.INTERNAL_MATCHES[1]);
							if (s_int_txt != tot.INTERNAL_MATCHES[1])
								s_int.effect("highlight", {}, 1000);

							var s_tm75 = $('.stat_tm75', context);
							s_tm75_txt = s_tm75.text();
							s_tm75.text(tot.TM_75_99[1]);
							if (s_tm75_txt != tot.TM_75_99[1])
								s_tm75.effect("highlight", {}, 1000);

							var s_tm100 = $('.stat_tm100', context);
							s_tm100_txt = s_tm100.text();
							s_tm100.text(tot.TM_100[1]);
							if (s_tm100_txt != tot.TM_100[1])
								s_tm100.effect("highlight", {}, 1000);

							var s_tmic = $('.stat_tmic', context);
							s_tmic_txt = s_tmic.text();
							s_tmic.text(tot.ICE[1]);
							if (s_tmic_txt != tot.ICE[1])
								s_tmic.effect("highlight", {}, 1000);

							var s_mt = $('.stat_mt', context);
							s_mt_txt = s_mt.text();
							s_mt.text(tot.MT[1]);
							if (s_mt_txt != tot.MT[1])
								s_mt.effect("highlight", {}, 1000);


						});

						$.each(files_group, function(jobpassword, files_object) {

							$.each(files_object, function(id_file, file_details) {

								context = $(global_context).find('#file_' + job_id + '_' + jobpassword + '_' + id_file);

                                var s_payable = $('.stat_payable strong', context );
                                var s_payable_txt = s_payable.text();
                                s_payable.text( file_details.TOTAL_PAYABLE[1] );
                                if ( s_payable_txt != file_details.TOTAL_PAYABLE[1] )
                                    s_payable.effect( "highlight", {}, 1000 );

								var s_new = $('.stat_new', context);
								var s_new_txt = s_new.text();
								s_new.text(file_details.NEW[1]);
								if (s_new_txt != file_details.NEW[1])
									s_new.effect("highlight", {}, 1000);

								var s_rep = $('.stat_rep', context);
								s_rep_txt = s_rep.text();
								s_rep.text(file_details.REPETITIONS[1]);
								if (s_rep_txt != file_details.REPETITIONS[1])
									s_rep.effect("highlight", {}, 1000);

								var s_int = $('.stat_int', context);
								s_int_txt = s_int.text();
								s_int.text(file_details.INTERNAL_MATCHES[1]);
								if (s_int_txt != file_details.INTERNAL_MATCHES[1])
									s_int.effect("highlight", {}, 1000);

								var s_tm75 = $('.stat_tm75', context);
								s_tm75_txt = s_tm75.text();
								s_tm75.text(file_details.TM_75_99[1]);
								if (s_tm75_txt != file_details.TM_75_99[1])
									s_tm75.effect("highlight", {}, 1000);

								var s_tm100 = $('.stat_tm100', context);
								s_tm100_txt = s_tm100.text();
								s_tm100.text(file_details.TM_100[1]);
								if (s_tm100_txt != file_details.TM_100[1])
									s_tm100.effect("highlight", {}, 1000);

								var s_tmic = $('.stat_tmic', context);
								s_tmic_txt = s_tmic.text();
								s_tmic.text(file_details.ICE[1]);
								if (s_tmic_txt != file_details.ICE[1])
									s_tmic.effect("highlight", {}, 1000);

								var s_mt = $('.stat_mt', context);
								s_mt_txt = s_mt.text();
								s_mt.text(file_details.MT[1]);
								if (s_mt_txt != file_details.MT[1])
									s_mt.effect("highlight", {}, 1000);


							});

						});

					});

					if (d.data.summary.STATUS != 'DONE') {
						$('.dosplit').addClass('disabled');
                        if( d.data.summary.TOTAL_SEGMENTS > UI.segmentsThreshold  ){
                            UI.pollingTime = parseInt( d.data.summary.TOTAL_SEGMENTS / 20 ) ;
                            console.log( 'Polling time: ' + UI.pollingTime );
                        }
						setTimeout(function() {
							UI.pollData();
						}, UI.pollingTime );
					} else {
						$('.dosplit').removeClass('disabled');
						$('#longloading .approved-bar').css('width', '100%');
						$('#analyzedSegmentsReport').text(s.SEGMENTS_ANALYZED_PRINT);
						setTimeout(function() {
							$('#shortloading').remove();
							$('#longloading .meter').remove();
							$('#longloading').show();
							$('#longloading p').addClass('loaded').text('Analysis complete');
						}, 1000);
						/*
						 setTimeout(function(){
						 $('.loadingbar').removeClass('open');
						 },2000);     
						 */
					}

				}
			}
		});

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

$(document).ready(function() {
	APP.init();
	if (config.showModalBoxLogin == 1) {
		$('#popupWrapper').fadeToggle();
	}
	$('#sign-in').click(function(e) {
		e.preventDefault();
		gopopup($(e.target).data('oauth'));
	});
	UI.init();
});
