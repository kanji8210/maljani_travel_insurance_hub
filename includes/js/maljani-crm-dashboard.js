jQuery(document).ready(function($) {
    const api = maljaniCrmParams.rest_url;
    const headers = { 'X-WP-Nonce': maljaniCrmParams.nonce };

    // Tab switching
    $('.crm-tab').on('click', function() {
        $('.crm-tab').removeClass('active');
        $(this).addClass('active');
        $('.crm-section').removeClass('active');
        $('#crm-' + $(this).data('target')).addClass('active');
        loadData($(this).data('target'));
    });

    function loadData(tab) {
        if (tab === 'clients') loadClients();
        if (tab === 'policies') loadPolicies();
        if (tab === 'payments') loadPayments();
    }

    // CLIENTS
    function loadClients() {
        $.ajax({url: api + '/clients', headers: headers}).done(res => {
            let html = '<table class="crm-table"><thead><tr><th>Name</th><th>Email</th><th>Passport</th></tr></thead><tbody>';
            $('#crm-client-select').empty(); // populate select dropdown too
            res.clients.forEach(c => {
                html += `<tr><td>${c.first_name} ${c.last_name}</td><td>${c.email}</td><td>${c.passport_number}</td></tr>`;
                $('#crm-client-select').append(`<option value="${c.id}">${c.first_name} ${c.last_name}</option>`);
            });
            html += '</tbody></table>';
            $('#crm-clients-list').html(html);
        });
    }

    $('#crm-add-client-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: api + '/clients', method: 'POST', headers: headers, data: $(this).serialize()
        }).done(res => {
            hideAllModals();
            loadClients();
            this.reset();
        });
    });

    // POLICIES
    function loadPolicies() {
        $.ajax({url: api + '/policies', headers: headers}).done(res => {
            let html = '<table class="crm-table"><thead><tr><th>ID</th><th>Client</th><th>Premium</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            res.policies.forEach(p => {
                html += `<tr>
                    <td>#${p.id}</td>
                    <td>${p.first_name} ${p.last_name}</td>
                    <td>$${p.premium}</td>
                    <td><span class="crm-badge crm-badge-${p.workflow_status}">${p.workflow_status.replace(/_/g, ' ').toUpperCase()}</span></td>
                    <td>`;
                
                if (p.workflow_status === 'draft') {
                    html += `<button class="crm-btn crm-btn-small" onclick="submitPolicy(${p.id})">Submit to Maljani</button>`;
                } else if (p.workflow_status === 'active' || p.workflow_status === 'verification_ready') {
                    html += `<a href="#" class="crm-btn crm-btn-small crm-btn-primary">Download Docs</a>`;
                } else {
                    html += `<span class="crm-text-muted">In Progress</span>`;
                }

                html += `</td></tr>`;
            });
            html += '</tbody></table>';
            $('#crm-policies-list').html(html);
        });
    }

    $('#crm-create-policy-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: api + '/policies', method: 'POST', headers: headers, data: $(this).serialize()
        }).done(res => {
            hideAllModals();
            loadPolicies();
            this.reset();
        });
    });

    window.submitPolicy = function(id) {
        if(!confirm('Submit to Maljani for review?')) return;
        $.ajax({
            url: api + '/policies/' + id + '/transition',
            method: 'POST', headers: headers, data: {target_status: 'pending_review'}
        }).done(res => loadPolicies());
    }

    // PAYMENTS / COMMISSIONS
    function loadPayments() {
        $.ajax({url: api + '/payments', headers: headers}).done(res => {
            const statusColors = {
                unpaid: { label: 'UNPAID', color: '#f59e0b' },
                paid: { label: 'PAID', color: '#10b981' },
                received: { label: 'RECEIVED', color: '#3b82f6' },
                disputed: { label: 'DISPUTED', color: '#ef4444' }
            };

            let html = '<table class="crm-table"><thead><tr><th>Policy</th><th>Premium</th><th>Comm Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            if (res.payments.length === 0) {
                html += '<tr><td colspan="5" style="text-align:center;color:#64748b;">No commissions recorded yet.</td></tr>';
            }
            res.payments.forEach(p => {
                const st = statusColors[p.status] || statusColors.unpaid;
                html += `<tr>
                    <td>${p.policy_number}<br><small>${p.insured_names}</small></td>
                    <td>$${parseFloat(p.premium).toFixed(2)}</td>
                    <td><strong>$${parseFloat(p.amount).toFixed(2)}</strong></td>
                    <td><span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;color:${st.color}">${st.label}</span></td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">`;

                // Mark Received — only available when status is 'paid'
                if (p.status === 'paid') {
                    html += `<button class="crm-btn crm-btn-small" onclick="markCommReceived(${p.id})">✓ Mark Received</button>`;
                }

                // Dispute — only when 'paid' or 'received'
                if (p.status === 'paid' || p.status === 'received') {
                    html += `<button class="crm-btn crm-btn-small crm-btn-danger" onclick="disputeComm(${p.id})">⚠ Dispute</button>`;
                }

                if (p.status === 'unpaid') {
                    html += `<span style="color:#94a3b8;font-size:12px;">Awaiting payment</span>`;
                }

                html += `</td></tr>`;
            });
            html += '</tbody></table>';
            $('#crm-commissions-list').html(html);
        });
    }

    window.markCommReceived = function(id) {
        if (!confirm('Mark this commission as received from the insurer?')) return;
        $.ajax({
            url: api + '/commissions/' + id + '/mark-received',
            method: 'POST', headers: headers
        }).done(res => {
            if (res.success) { loadPayments(); }
            else { alert('Error: ' + (res.message || 'Could not update status')); }
        }).fail(() => alert('Request failed. Please try again.'));
    };

    window.disputeComm = function(id) {
        let reason = prompt('Please provide a reason for the dispute:');
        if (!reason) return;

        $.ajax({
            url: api + '/commissions/' + id + '/dispute',
            method: 'POST', headers: headers, data: {reason: reason}
        }).done(res => {
            if (res.success) { alert('Dispute submitted.'); loadPayments(); }
            else { alert('Error: ' + (res.message || 'Could not submit dispute')); }
        }).fail(() => alert('Request failed. Please try again.'));
    }

    // Initial load
    loadClients();
});
