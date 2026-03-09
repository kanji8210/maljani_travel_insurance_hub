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

    // Initial load
    loadClients();
});
