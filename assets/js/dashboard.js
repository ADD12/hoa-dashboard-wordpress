(function ($) {
	'use strict';

	function ajax(action, data, done, fail) {
		data = data || {};
		data.action = action;
		data.nonce = HOA_DASH.nonce;
		$.post(HOA_DASH.ajax_url, data)
			.done(function (res) {
				if (res && res.success) { done && done(res.data); }
				else { fail && fail(res && res.data ? res.data : 'error'); }
			})
			.fail(function () { fail && fail('network_error'); });
	}

	/* ---------------- LOGIN FLOW ---------------- */
	function initLogin() {
		var $step1 = $('#hoa-login-form-step1');
		var $step2 = $('#hoa-login-form-step2');
		if (!$step1.length) return;

		$step1.on('submit', function (e) {
			e.preventDefault();
			var $err = $step1.find('.hoa-login-error').hide();
			$.post(HOA_DASH.ajax_url, {
				action: 'hoa_login_step1',
				nonce: $step1.find('[name=nonce]').val(),
				username: $step1.find('[name=username]').val(),
				password: $step1.find('[name=password]').val()
			}).done(function (res) {
				if (!res.success) { $err.text(res.data).show(); return; }
				if (res.data.requires_2fa) {
					$step1.hide();
					$step2.show().find('[name=pending_uid]').val(res.data.pending_uid);
					ajax('hoa_2fa_send_code', { pending_uid: res.data.pending_uid, nonce: $step2.find('[name=nonce]').val() }, function () {}, function () {});
				} else {
					window.location = res.data.redirect;
				}
			}).fail(function () { $err.text('Network error, please try again.').show(); });
		});

		$step2.on('submit', function (e) {
			e.preventDefault();
			var $err = $step2.find('.hoa-login-error').hide();
			$.post(HOA_DASH.ajax_url, {
				action: 'hoa_2fa_verify_code',
				nonce: $step2.find('[name=nonce]').val(),
				pending_uid: $step2.find('[name=pending_uid]').val(),
				code: $step2.find('[name=code]').val()
			}).done(function (res) {
				if (!res.success) { $err.text(res.data).show(); return; }
				window.location = res.data.redirect;
			}).fail(function () { $err.text('Network error, please try again.').show(); });
		});
	}

	/* ---------------- TABS ---------------- */
	function initTabs() {
		$('.hoa-tab-btn').on('click', function () {
			var tab = $(this).data('tab');
			$('.hoa-tab-btn').removeClass('active');
			$(this).addClass('active');
			$('.hoa-tab-panel').hide();
			$('#tab-' + tab).show();
			loadTab(tab);
		});
	}

	var loaded = {};
	function loadTab(tab) {
		if (loaded[tab]) return;
		loaded[tab] = true;
		if (tab === 'overview') { loadOverview(); }
		if (tab === 'reserves') { loadReserves(); }
		if (tab === 'dues') { loadDues(); }
		if (tab === 'tickets') { loadMyTickets(); }
		if (tab === 'calendar') { loadEvents(true); }
		if (tab === 'newsletter') { loadNewsletters(); }
		if (tab === 'board') { loadAllTickets(); loadAuditLog(); loadPmReviews(); }
	}

	/* ---------------- OVERVIEW ---------------- */
	var healthChart = null;
	function loadOverview() {
		ajax('hoa_get_reserve_health', {}, function (data) {
			var $badge = $('#hoa-health-badge');
			$badge.removeClass('green yellow red').addClass(data.overall_status);
			$badge.text(data.overall_status.toUpperCase() + ' — ' + data.overall_percent_funded + '% funded');

			var ctx = document.getElementById('hoa-health-chart');
			if (ctx && window.Chart) {
				var colors = { green: '#1c7c34', yellow: '#b8860b', red: '#c0392b' };
				if (healthChart) healthChart.destroy();
				healthChart = new Chart(ctx, {
					type: 'doughnut',
					data: {
						labels: ['Funded', 'Remaining to Target'],
						datasets: [{
							data: [data.total_balance, Math.max(0, data.total_target - data.total_balance)],
							backgroundColor: [colors[data.overall_status], '#e4e7eb']
						}]
					},
					options: { plugins: { legend: { position: 'bottom' } } }
				});
			}
		});

		ajax('hoa_get_my_dues', {}, function (data) {
			$('#hoa-dues-summary').html('<div class="hoa-balance-box">$' + Number(data.balance_owed).toFixed(2) + ' owed</div>');
		});

		loadEvents(false);
	}

	/* ---------------- RESERVES ---------------- */
	function statusPill(status) {
		return '<span class="hoa-status-pill ' + status + '">' + status.toUpperCase() + '</span>';
	}

	function loadReserves() {
		ajax('hoa_get_reserve_health', {}, function (data) {
			var $tbody = $('#hoa-reserve-table tbody').empty();
			data.accounts.forEach(function (a) {
				var actions = (HOA_DASH.is_board || HOA_DASH.is_pm)
					? '<td><button class="hoa-btn hoa-edit-reserve" data-id="' + a.id + '">Edit</button></td>' : '';
				$tbody.append('<tr>' +
					'<td>' + a.account_name + '</td>' +
					'<td>' + a.account_category + '</td>' +
					'<td>$' + Number(a.current_balance).toLocaleString() + '</td>' +
					'<td>$' + Number(a.fully_funded_target).toLocaleString() + '</td>' +
					'<td>$' + Number(a.ca_min_required).toLocaleString() + (a.below_ca_min ? ' ⚠' : '') + '</td>' +
					'<td>' + a.percent_funded + '%</td>' +
					'<td>' + statusPill(a.status) + '</td>' +
					'<td>' + (a.statement_date || '') + '</td>' +
					actions +
					'</tr>');
			});
			window._hoaReserveAccounts = data.accounts;

			if ($('#hoa-threshold-form').length) {
				$('#hoa-threshold-form [name=green_min_pct]').val(data.thresholds.green_min_pct);
				$('#hoa-threshold-form [name=yellow_min_pct]').val(data.thresholds.yellow_min_pct);
			}
		});
	}

	$(document).on('click', '.hoa-edit-reserve', function () {
		var id = $(this).data('id');
		var acct = (window._hoaReserveAccounts || []).find(function (a) { return String(a.id) === String(id); });
		if (!acct) return;
		var $f = $('#hoa-reserve-form');
		$f.find('[name=id]').val(acct.id);
		$f.find('[name=account_name]').val(acct.account_name);
		$f.find('[name=account_category]').val(acct.account_category);
		$f.find('[name=current_balance]').val(acct.current_balance);
		$f.find('[name=fully_funded_target]').val(acct.fully_funded_target);
		$f.find('[name=ca_min_required]').val(acct.ca_min_required);
		$f.find('[name=statement_date]').val(acct.statement_date);
		$f.find('[name=source_document]').val(acct.source_document);
		$f.find('[name=notes]').val(acct.notes);
	});

	$(document).on('submit', '#hoa-reserve-form', function (e) {
		e.preventDefault();
		var data = {};
		$(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
		ajax('hoa_save_reserve_account', data, function () {
			loaded.reserves = false; loadReserves();
			$('#hoa-reserve-form')[0].reset();
		}, function (err) { alert('Error: ' + err); });
	});

	$(document).on('submit', '#hoa-threshold-form', function (e) {
		e.preventDefault();
		var data = {};
		$(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
		ajax('hoa_save_thresholds', data, function () {
			loaded.reserves = false; loadReserves();
			loaded.overview = false; loadOverview();
		}, function (err) { alert('Error: ' + err); });
	});

	/* ---------------- DUES & PAYMENTS ---------------- */
	var currentLedgerId = null;
	function loadDues() {
		ajax('hoa_get_my_dues', {}, function (data) {
			$('#hoa-dues-balance').text('$' + Number(data.balance_owed).toFixed(2) + ' owed');
			var $tbody = $('#hoa-dues-ledger-table tbody').empty();
			data.ledger.forEach(function (r) {
				$tbody.append('<tr><td>' + r.due_date + '</td><td>$' + Number(r.amount_due).toFixed(2) + '</td><td>$' + Number(r.amount_paid).toFixed(2) + '</td><td>' + r.status + '</td></tr>');
			});
			var unpaid = data.ledger.find(function (r) { return r.status !== 'paid'; });
			currentLedgerId = unpaid ? unpaid.id : null;
			if (unpaid) $('#hoa-pay-form [name=amount]').val((unpaid.amount_due - unpaid.amount_paid).toFixed(2));
		});
	}

	$('#hoa-pay-now-btn').on('click', function () { $('#hoa-pay-modal').show(); });
	$('.hoa-modal-close').on('click', function () { $(this).closest('.hoa-modal').hide(); });

	function updateFeePreview() {
		var amount = parseFloat($('#hoa-pay-form [name=amount]').val()) || 0;
		var method = $('#hoa-pay-form [name=method]:checked').val();
		var fee = method === 'card' ? (amount * 0.029 + 0.30) : 1.00; // client-side estimate; server computes authoritative fee
		$('#hoa-fee-preview').text('Estimated processing fee: $' + fee.toFixed(2) + ' — total charge: $' + (amount + fee).toFixed(2));
	}
	$(document).on('input change', '#hoa-pay-form [name=amount], #hoa-pay-form [name=method]', updateFeePreview);

	$('#hoa-pay-form').on('submit', function (e) {
		e.preventDefault();
		var payload = {
			amount: $(this).find('[name=amount]').val(),
			method: $(this).find('[name=method]:checked').val(),
			gateway_token: $(this).find('[name=mock_token_input]').val() || 'tok_placeholder',
			ledger_id: currentLedgerId
		};
		ajax('hoa_pay_now', payload, function (data) {
			alert('Payment successful. Reference: ' + data.reference + '. Total charged: $' + data.total_charged);
			if ($('#hoa-autopay-checkbox').is(':checked')) {
				ajax('hoa_setup_autopay', {
					funding_type: payload.method,
					gateway_token: payload.gateway_token,
					day_of_month: new Date().getDate()
				}, function () { alert('Autopay enabled for future dues.'); });
			}
			$('#hoa-pay-modal').hide();
			loaded.dues = false; loadDues();
		}, function (err) { alert('Payment failed: ' + err); });
	});

	/* ---------------- TICKETS ---------------- */
	function loadMyTickets() {
		ajax('hoa_get_my_tickets', {}, function (rows) {
			var $tbody = $('#hoa-my-tickets-table tbody').empty();
			rows.forEach(function (t) {
				$tbody.append('<tr><td>' + t.ticket_number + '</td><td>' + t.subject + '</td><td>' + t.status + '</td><td>' + t.created_at + '</td></tr>');
			});
		});
	}

	$('#hoa-ticket-form').on('submit', function (e) {
		e.preventDefault();
		var data = {};
		$(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
		ajax('hoa_submit_ticket', data, function (res) {
			$('#hoa-ticket-confirmation').show().text('Ticket submitted. Your ticket number is ' + res.ticket_number + '.');
			$('#hoa-ticket-form')[0].reset();
			loaded.tickets = false; loadMyTickets();
		}, function (err) { alert('Error: ' + err); });
	});

	/* ---------------- CALENDAR ---------------- */
	function loadEvents(fullList) {
		ajax('hoa_get_events', {}, function (rows) {
			if (fullList) {
				var $list = $('#hoa-events-list').empty();
				if (!rows.length) { $list.text('No upcoming events.'); return; }
				rows.forEach(function (ev) {
					$list.append(
						'<div class="hoa-event-card"><h4>' + ev.title + '</h4>' +
						'<div>' + ev.start_datetime + '</div>' +
						(ev.location_text ? '<div>📍 ' + ev.location_text + '</div>' : '') +
						(ev.zoom_link ? '<div>💻 <a href="' + ev.zoom_link + '" target="_blank" rel="noopener">Join Zoom</a></div>' : '') +
						'</div>'
					);
				});
			} else {
				var $next = $('#hoa-next-event');
				if (!rows.length) { $next.text('No upcoming meetings scheduled.'); return; }
				var ev = rows[0];
				$next.html('<strong>' + ev.title + '</strong> — ' + ev.start_datetime +
					(ev.zoom_link ? ' — <a href="' + ev.zoom_link + '" target="_blank" rel="noopener">Zoom link</a>' : '') +
					(ev.location_text ? ' — ' + ev.location_text : ''));
			}
		});
	}

	$('#hoa-event-form').on('submit', function (e) {
		e.preventDefault();
		var data = {};
		$(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
		ajax('hoa_save_event', data, function () {
			loaded.calendar = false; loadEvents(true);
			$('#hoa-event-form')[0].reset();
		}, function (err) { alert('Error: ' + err); });
	});

	/* ---------------- NEWSLETTER ---------------- */
	function loadNewsletters() {
		ajax('hoa_get_newsletters', {}, function (rows) {
			var $list = $('#hoa-newsletter-list').empty();
			rows.forEach(function (n) {
				$list.append('<div class="hoa-event-card"><h4>' + n.subject + ' (' + n.status + ')</h4><div>' + n.body_html + '</div></div>');
			});
		});
	}

	$('#hoa-generate-newsletter-btn').on('click', function () {
		ajax('hoa_generate_newsletter_draft', {}, function (data) {
			$('#hoa-newsletter-form [name=body_html]').val(data.body_html);
		});
	});

	$('#hoa-newsletter-form').on('submit', function (e) {
		e.preventDefault();
		var data = {};
		$(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });
		data.period_month = (data.period_month_picker || '') + '-01';
		ajax('hoa_save_newsletter', data, function (res) {
			$('#hoa-newsletter-form [name=id]').val(res.id);
			alert('Draft saved.');
		}, function (err) { alert('Error: ' + err); });
	});

	$('#hoa-send-newsletter-btn').on('click', function () {
		var id = $('#hoa-newsletter-form [name=id]').val();
		if (!id) { alert('Save the draft first.'); return; }
		if (!confirm('Send this newsletter to all members now?')) return;
		ajax('hoa_send_newsletter', { id: id }, function (res) {
			alert('Sent to ' + res.sent_to + ' members.');
			loaded.newsletter = false; loadNewsletters();
		}, function (err) { alert('Error: ' + err); });
	});

	/* ---------------- BOARD TOOLS ---------------- */
	function loadAllTickets() {
		ajax('hoa_get_all_tickets', {}, function (data) {
			$('#hoa-ticket-summary').html(
				'<div>Open: ' + data.summary.total_open + '</div>' +
				'<div>Closed: ' + data.summary.total_closed + '</div>' +
				'<div>Escalated: ' + data.summary.total_escalated + '</div>'
			);
			var $tbody = $('#hoa-all-tickets-table tbody').empty();
			data.tickets.forEach(function (t) {
				$tbody.append('<tr>' +
					'<td>' + t.ticket_number + '</td><td>' + (t.display_name || '') + '</td><td>' + t.subject + '</td>' +
					'<td>' + t.status + '</td><td>' + t.created_at + '</td><td>' + (t.pm_responded_at || '—') + '</td>' +
					'<td>' + (t.closed_at || '—') + '</td><td>' + (t.escalated == 1 ? 'Yes' : 'No') + '</td>' +
					'<td>' +
						'<button class="hoa-btn hoa-ticket-close" data-id="' + t.id + '">Close</button> ' +
						'<button class="hoa-btn hoa-ticket-escalate" data-id="' + t.id + '">Escalate</button>' +
					'</td>' +
					'</tr>');
			});
		});
	}

	$(document).on('click', '.hoa-ticket-close', function () {
		ajax('hoa_ticket_close', { ticket_id: $(this).data('id') }, function () { loaded.board = false; loadAllTickets(); });
	});
	$(document).on('click', '.hoa-ticket-escalate', function () {
		var reason = prompt('Reason for escalating to the property management firm:');
		if (!reason) return;
		ajax('hoa_escalate_ticket', { ticket_id: $(this).data('id'), reason: reason }, function () { loaded.board = false; loadAllTickets(); });
	});

	function loadAuditLog() {
		ajax('hoa_get_pm_audit_log', {}, function (rows) {
			var $tbody = $('#hoa-audit-log-table tbody').empty();
			rows.forEach(function (r) {
				$tbody.append('<tr><td>' + r.created_at + '</td><td>' + (r.display_name || '') + '</td><td>' + r.action + '</td><td>' + (r.object_type || '') + ' #' + (r.object_id || '') + '</td><td>' + (r.ip_address || '') + '</td></tr>');
			});
		});
	}

	function loadPmReviews() {
		ajax('hoa_get_pm_reviews', {}, function (data) {
			$('#hoa-pm-review-avg').text(data.average_rating ? ('Average rating: ' + data.average_rating + ' / 5') : 'No reviews yet.');
			var $tbody = $('#hoa-pm-reviews-table tbody').empty();
			data.reviews.forEach(function (r) {
				$tbody.append('<tr><td>' + (r.display_name || '') + '</td><td>' + (r.ticket_number || '') + '</td><td>' + r.rating + '/5</td><td>' + (r.comments || '') + '</td><td>' + r.created_at + '</td></tr>');
			});
		});
	}

	/* ---------------- 2FA ENROLLMENT ---------------- */
	$('#hoa-2fa-enroll-form').on('submit', function (e) {
		e.preventDefault();
		ajax('hoa_2fa_enroll_phone', { phone: $(this).find('[name=phone]').val() }, function () {
			$('#hoa-2fa-status').text('Code sent. Enter it below.');
			$('#hoa-2fa-confirm-form').show();
		}, function (err) { $('#hoa-2fa-status').text('Error: ' + err); });
	});
	$('#hoa-2fa-confirm-form').on('submit', function (e) {
		e.preventDefault();
		ajax('hoa_2fa_confirm_enroll', { code: $(this).find('[name=code]').val() }, function (data) {
			$('#hoa-2fa-status').text(data.message);
		}, function (err) { $('#hoa-2fa-status').text('Error: ' + err); });
	});

	$(function () {
		initLogin();
		initTabs();
		if ($('#hoa-dashboard-app').length) { loadTab('overview'); }
	});

})(jQuery);
